<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Media;
use App\Services\Audit\AuditLogger;
use App\Services\Storage\EncryptedBlobStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private EncryptedBlobStorage $blobs,
    ) {}

    public function index(Request $request, Gallery $gallery)
    {
        $this->authorize('view', $gallery);

        // Return metadata only — no ciphertext blobs
        $media = $gallery->media()->latest()->get()->map(fn ($m) => [
            'id' => $m->id,
            'encrypted_title' => $m->encrypted_title,
            'title_iv' => $m->title_iv,
            'encrypted_caption' => $m->encrypted_caption,
            'caption_iv' => $m->caption_iv,
            'mime_type' => $m->mime_type,
            'size' => $m->size,
            'has_thumbnail' => $m->thumb_path !== null,
            'uploaded_by' => $m->uploaded_by,
            'created_at' => $m->created_at->toISOString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['media' => $media]);
        }

        return view('galleries.show', [
            'workspace' => $gallery->collection->workspace,
            'collection' => $gallery->collection,
            'gallery' => $gallery,
            'media' => $media,
        ]);
    }

    public function store(Request $request, Gallery $gallery)
    {
        $this->authorize('create', [Media::class, $gallery]);

        $validated = $request->validate([
            'ciphertext' => ['required', 'file', 'max:102400'], // 100MB ciphertext (video support)
            'thumbnail' => ['nullable', 'file', 'max:2048'],
            'cek_wrapped' => ['required', 'string'],
            'iv' => ['required', 'string'],
            'thumb_iv' => ['nullable', 'string'],
            'mime_type' => ['required', 'string', 'max:100'],
            'size' => ['required', 'integer', 'min:1'],
            'encrypted_title' => ['nullable', 'string'],
            'title_iv' => ['nullable', 'string'],
            'encrypted_caption' => ['nullable', 'string'],
            'caption_iv' => ['nullable', 'string'],
        ]);

        $mediaId = (string) Str::uuid();
        $workspace = $gallery->collection->workspace;

        $blobPath = $this->blobs->blobPath($workspace->id, $gallery->id, $mediaId);
        $this->blobs->write($blobPath, $request->file('ciphertext')->get());

        $thumbPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbPath = $this->blobs->thumbPath($workspace->id, $gallery->id, $mediaId);
            $this->blobs->write($thumbPath, $request->file('thumbnail')->get());
        }

        $media = Media::create([
            'id' => $mediaId,
            'gallery_id' => $gallery->id,
            'uploaded_by' => $request->user()->id,
            'blob_path' => $blobPath,
            'thumb_path' => $thumbPath,
            'cek_wrapped' => $validated['cek_wrapped'],
            'iv' => $validated['iv'],
            'thumb_iv' => $validated['thumb_iv'] ?? null,
            'mime_type' => $validated['mime_type'],
            'size' => $validated['size'],
            'encrypted_title' => $validated['encrypted_title'] ?? null,
            'title_iv' => $validated['title_iv'] ?? null,
            'encrypted_caption' => $validated['encrypted_caption'] ?? null,
            'caption_iv' => $validated['caption_iv'] ?? null,
        ]);

        $this->audit->log($request->user(), $media, 'media.uploaded', [
            'size' => $media->size,
            'mime_type' => $media->mime_type,
        ]);

        return response()->json(['media_id' => $media->id], 201);
    }

    public function show(Request $request, Media $medium)
    {
        $this->authorize('view', $medium);

        return view('media.show', [
            'media' => $medium,
            'gallery' => $medium->gallery,
            'collection' => $medium->gallery->collection,
            'workspace' => $medium->gallery->collection->workspace,
        ]);
    }

    public function update(Request $request, Media $medium)
    {
        $this->authorize('update', $medium);

        $validated = $request->validate([
            'encrypted_title' => ['nullable', 'string'],
            'title_iv' => ['nullable', 'string'],
            'encrypted_caption' => ['nullable', 'string'],
            'caption_iv' => ['nullable', 'string'],
        ]);

        $medium->update($validated);

        $this->audit->log($request->user(), $medium, 'media.renamed');

        return response()->json(['status' => 'updated']);
    }

    public function blob(Request $request, Media $medium)
    {
        $this->authorize('view', $medium);

        if (!$this->blobs->exists($medium->blob_path)) {
            abort(404);
        }

        $response = $this->blobs->stream($medium->blob_path);
        $response->headers->set('X-Cek-Wrapped', $medium->cek_wrapped);
        $response->headers->set('X-Blob-Iv', $medium->iv);

        return $response;
    }

    public function thumbnail(Request $request, Media $medium)
    {
        $this->authorize('view', $medium);

        if (!$medium->thumb_path || !$this->blobs->exists($medium->thumb_path)) {
            abort(404);
        }

        $response = $this->blobs->stream($medium->thumb_path);
        $response->headers->set('X-Cek-Wrapped', $medium->cek_wrapped);
        $response->headers->set('X-Blob-Iv', $medium->thumb_iv);

        return $response;
    }

    public function destroy(Request $request, Media $medium)
    {
        $this->authorize('delete', $medium);

        $this->audit->log($request->user(), $medium, 'media.deleted');

        $this->blobs->delete($medium->blob_path);
        $this->blobs->delete($medium->thumb_path);
        $medium->delete();

        return response()->json(['status' => 'deleted']);
    }
}
