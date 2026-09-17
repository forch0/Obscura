<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Gallery;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function create(Request $request, Collection $collection)
    {
        $this->authorize('update', $collection->workspace);

        return view('galleries.create', [
            'workspace' => $collection->workspace,
            'collection' => $collection,
        ]);
    }

    public function store(Request $request, Collection $collection)
    {
        $this->authorize('update', $collection->workspace);

        $validated = $request->validate([
            'encrypted_name' => ['required', 'string'],
            'name_iv' => ['required', 'string'],
            'type' => ['required', 'string', 'in:private,shared,joint'],
            'encrypted_description' => ['nullable', 'string'],
            'description_iv' => ['nullable', 'string'],
        ]);

        $gallery = $collection->galleries()->create($validated);

        $this->audit->log($request->user(), $gallery, 'gallery.created', [
            'type' => $gallery->type,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['gallery_id' => $gallery->id], 201);
        }

        return redirect()->route('galleries.show', [$collection, $gallery]);
    }

    public function show(Request $request, Collection $collection, Gallery $gallery)
    {
        if ($gallery->collection_id !== $collection->id) {
            abort(404);
        }

        $resolver = app(\App\Services\Authorization\AuthorizationResolver::class);
        if (!$resolver->check($request->user(), $gallery, 'view')) {
            abort(403);
        }

        return view('galleries.show', [
            'workspace' => $collection->workspace,
            'collection' => $collection,
            'gallery' => $gallery,
        ]);
    }

    public function edit(Request $request, Collection $collection, Gallery $gallery)
    {
        if ($gallery->collection_id !== $collection->id) {
            abort(404);
        }

        $resolver = app(\App\Services\Authorization\AuthorizationResolver::class);
        if (!$resolver->check($request->user(), $gallery, 'update')) {
            abort(403);
        }

        return view('galleries.edit', [
            'workspace' => $collection->workspace,
            'collection' => $collection,
            'gallery' => $gallery,
        ]);
    }

    public function update(Request $request, Collection $collection, Gallery $gallery)
    {
        if ($gallery->collection_id !== $collection->id) {
            abort(404);
        }

        $resolver = app(\App\Services\Authorization\AuthorizationResolver::class);
        if (!$resolver->check($request->user(), $gallery, 'update')) {
            abort(403);
        }

        $validated = $request->validate([
            'encrypted_name' => ['required', 'string'],
            'name_iv' => ['required', 'string'],
            'type' => ['required', 'string', 'in:private,shared,joint'],
            'encrypted_description' => ['nullable', 'string'],
            'description_iv' => ['nullable', 'string'],
        ]);

        $gallery->update($validated);

        $this->audit->log($request->user(), $gallery, 'gallery.renamed');

        if ($request->expectsJson()) {
            return response()->json(['status' => 'updated']);
        }

        return redirect()->route('galleries.show', [$collection, $gallery]);
    }

    public function destroy(Request $request, Collection $collection, Gallery $gallery)
    {
        if ($gallery->collection_id !== $collection->id) {
            abort(404);
        }

        $resolver = app(\App\Services\Authorization\AuthorizationResolver::class);
        if (!$resolver->check($request->user(), $gallery, 'delete')) {
            abort(403);
        }

        $this->audit->log($request->user(), $gallery, 'gallery.deleted');

        $gallery->delete();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return redirect()->route('collections.show', [$collection->workspace, $collection]);
    }
}
