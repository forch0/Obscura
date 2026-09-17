<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
        $this->authorizeResource(Workspace::class, 'workspace');
    }

    public function index(Request $request)
    {
        $workspaces = $request->user()->is_super_admin
            ? Workspace::latest()->get()
            : Workspace::where('owner_id', $request->user()->id)
                ->orWhereHas('members', fn ($q) => $q->where('user_id', $request->user()->id))
                ->latest()->get();

        return view('workspaces.index', ['workspaces' => $workspaces]);
    }

    public function create()
    {
        return view('workspaces.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'encrypted_name' => ['required', 'string'],
            'name_iv' => ['required', 'string'],
            'wrapped_dek' => ['required', 'string'],
            'wrapped_dek_iv' => ['nullable', 'string'],
        ]);

        $workspace = Workspace::create([
            'owner_id' => $request->user()->id,
            'encrypted_name' => $validated['encrypted_name'],
            'name_iv' => $validated['name_iv'],
            'wrapped_dek_for_owner' => $validated['wrapped_dek'],
            'wrapped_dek_iv' => $validated['wrapped_dek_iv'] ?? '',
            'dek_version' => 1,
        ]);

        $this->audit->log($request->user(), $workspace, 'workspace.created');
        $this->audit->log($request->user(), $workspace, 'workspace.dek_stored');

        if ($request->expectsJson()) {
            return response()->json(['workspace_id' => $workspace->id], 201);
        }

        return redirect()->route('workspaces.show', $workspace);
    }

    public function show(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        return view('workspaces.show', ['workspace' => $workspace]);
    }

    public function edit(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        return view('workspaces.edit', ['workspace' => $workspace]);
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        $validated = $request->validate([
            'encrypted_name' => ['required', 'string'],
            'name_iv' => ['required', 'string'],
        ]);

        $workspace->update([
            'encrypted_name' => $validated['encrypted_name'],
            'name_iv' => $validated['name_iv'],
        ]);

        $this->audit->log($request->user(), $workspace, 'workspace.renamed');

        if ($request->expectsJson()) {
            return response()->json(['status' => 'renamed']);
        }

        return redirect()->route('workspaces.show', $workspace);
    }

    public function destroy(Request $request, Workspace $workspace)
    {
        $this->authorize('delete', $workspace);

        $this->audit->log($request->user(), $workspace, 'workspace.deleted');

        $workspace->delete();

        return redirect()->route('workspaces.index');
    }
}
