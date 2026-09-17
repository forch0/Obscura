<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        $collections = $workspace->collections()->latest()->get();

        return view('collections.index', [
            'workspace' => $workspace,
            'collections' => $collections,
        ]);
    }

    public function create(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        return view('collections.create', ['workspace' => $workspace]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        $validated = $request->validate([
            'encrypted_name' => ['required', 'string'],
            'name_iv' => ['required', 'string'],
            'encrypted_description' => ['nullable', 'string'],
            'description_iv' => ['nullable', 'string'],
        ]);

        $collection = $workspace->collections()->create([
            'encrypted_name' => $validated['encrypted_name'],
            'name_iv' => $validated['name_iv'],
            'encrypted_description' => $validated['encrypted_description'] ?? null,
            'description_iv' => $validated['description_iv'] ?? null,
        ]);

        $this->audit->log($request->user(), $collection, 'collection.created');

        if ($request->expectsJson()) {
            return response()->json(['collection_id' => $collection->id], 201);
        }

        return redirect()->route('collections.show', [$workspace, $collection]);
    }

    public function show(Request $request, Workspace $workspace, Collection $collection)
    {
        $this->authorize('view', $collection);

        if ($collection->workspace_id !== $workspace->id) {
            abort(404);
        }

        return view('collections.show', [
            'workspace' => $workspace,
            'collection' => $collection->load('galleries'),
        ]);
    }

    public function edit(Request $request, Workspace $workspace, Collection $collection)
    {
        $this->authorize('update', $collection);

        if ($collection->workspace_id !== $workspace->id) {
            abort(404);
        }

        return view('collections.edit', [
            'workspace' => $workspace,
            'collection' => $collection,
        ]);
    }

    public function update(Request $request, Workspace $workspace, Collection $collection)
    {
        $this->authorize('update', $collection);

        if ($collection->workspace_id !== $workspace->id) {
            abort(404);
        }

        $validated = $request->validate([
            'encrypted_name' => ['required', 'string'],
            'name_iv' => ['required', 'string'],
            'encrypted_description' => ['nullable', 'string'],
            'description_iv' => ['nullable', 'string'],
        ]);

        $collection->update($validated);

        $this->audit->log($request->user(), $collection, 'collection.renamed');

        if ($request->expectsJson()) {
            return response()->json(['status' => 'updated']);
        }

        return redirect()->route('collections.show', [$workspace, $collection]);
    }

    public function destroy(Request $request, Workspace $workspace, Collection $collection)
    {
        $this->authorize('delete', $collection);

        if ($collection->workspace_id !== $workspace->id) {
            abort(404);
        }

        $this->audit->log($request->user(), $collection, 'collection.deleted');

        $collection->delete();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return redirect()->route('collections.index', $workspace);
    }
}
