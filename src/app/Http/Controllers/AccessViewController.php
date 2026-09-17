<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\Workspace;
use App\Services\Storage\EncryptedBlobStorage;
use Illuminate\Http\Request;

/**
 * Guest-facing viewer for access-code sessions.
 * Authorization comes from the access_code:required middleware —
 * the payload (scope, scope_id, workspace_id) is merged onto the request.
 */
class AccessViewController extends Controller
{
    public function __construct(private EncryptedBlobStorage $blobs) {}

    public function show(Request $request)
    {
        $session = $request->input('access_scope');
        $code = $request->input('access_code');

        $workspace = Workspace::findOrFail($session['workspace_id']);

        $galleries = match ($session['scope']) {
            'gallery' => Gallery::where('id', $session['scope_id'])->get(),
            'collection' => Gallery::where('collection_id', $session['scope_id'])->get(),
            default => Gallery::whereHas(
                'collection',
                fn ($q) => $q->where('workspace_id', $workspace->id)
            )->get(),
        };

        return view('access.view', [
            'session' => $session,
            'code' => $code,
            'workspace' => $workspace,
            'galleries' => $galleries->map(fn ($g) => $g->only(['id', 'encrypted_name', 'name_iv', 'type']))->values(),
        ]);
    }

    public function media(Request $request, Gallery $gallery)
    {
        $this->authorizeGallery($request, $gallery);

        $media = $gallery->media()->latest()->get()->map(fn ($m) => [
            'id' => $m->id,
            'encrypted_title' => $m->encrypted_title,
            'title_iv' => $m->title_iv,
            'mime_type' => $m->mime_type,
            'size' => $m->size,
            'has_thumbnail' => $m->thumb_path !== null,
        ]);

        return response()->json(['media' => $media]);
    }

    public function blob(Request $request, Media $medium)
    {
        $this->authorizeMedia($request, $medium);
        abort_unless($this->blobs->exists($medium->blob_path), 404);

        $response = $this->blobs->stream($medium->blob_path);
        $response->headers->set('X-Cek-Wrapped', $medium->cek_wrapped);
        $response->headers->set('X-Blob-Iv', $medium->iv);

        return $response;
    }

    public function thumbnail(Request $request, Media $medium)
    {
        $this->authorizeMedia($request, $medium);
        abort_unless($medium->thumb_path && $this->blobs->exists($medium->thumb_path), 404);

        $response = $this->blobs->stream($medium->thumb_path);
        $response->headers->set('X-Cek-Wrapped', $medium->cek_wrapped);
        $response->headers->set('X-Blob-Iv', $medium->thumb_iv);

        return $response;
    }

    private function authorizeMedia(Request $request, Media $medium): void
    {
        $this->authorizeGallery($request, $medium->gallery);
    }

    private function authorizeGallery(Request $request, Gallery $gallery): void
    {
        $s = $request->input('access_scope');

        $allowed = match ($s['scope']) {
            'workspace' => $gallery->collection->workspace_id === $s['workspace_id'],
            'collection' => $gallery->collection_id === $s['scope_id'],
            'gallery' => $gallery->id === $s['scope_id'],
            default => false,
        };

        abort_unless($allowed, 403);
    }
}
