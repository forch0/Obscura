<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\GalleryMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceMemberController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        $members = $workspace->members()->with('user')->get();

        $eligible = User::whereNotNull('public_key')
            ->where('id', '!=', $workspace->owner_id)
            ->whereNotIn('id', $members->pluck('user_id'))
            ->orderBy('email')
            ->get(['id', 'email', 'public_key']);

        return view('workspaces.members', [
            'workspace' => $workspace,
            'members' => $members,
            'eligible' => $eligible,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        $validated = $request->validate([
            'user_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id')->where(fn ($q) => $q->whereNotNull('public_key')),
            ],
            'role' => ['required', 'string', 'in:editor,viewer'],
            'wrapped_dek' => ['required', 'string'],
        ]);

        if ($validated['user_id'] === $workspace->owner_id) {
            return back()->withErrors(['user_id' => 'The workspace owner already has access.']);
        }

        if ($workspace->members()->where('user_id', $validated['user_id'])->exists()) {
            return back()->withErrors(['user_id' => 'This user is already a workspace member.']);
        }

        $member = $workspace->members()->create([
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
            'wrapped_dek' => $validated['wrapped_dek'],
            'accepted_at' => now(),
        ]);

        $this->audit->log($request->user(), $workspace, 'workspace.member_added', [
            'member_id' => $member->id,
            'user_id' => $member->user_id,
            'role' => $member->role,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['member_id' => $member->id], 201);
        }

        return redirect()->route('workspaces.members', $workspace);
    }

    public function update(Request $request, Workspace $workspace, WorkspaceMember $member)
    {
        $this->authorize('update', $workspace);

        if ($member->workspace_id !== $workspace->id) {
            abort(404);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:editor,viewer'],
        ]);

        $member->update($validated);

        $this->audit->log($request->user(), $workspace, 'workspace.member_role_changed', [
            'member_id' => $member->id,
            'new_role' => $member->role,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'updated']);
        }

        return redirect()->route('workspaces.members', $workspace);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceMember $member)
    {
        $this->authorize('update', $workspace);

        if ($member->workspace_id !== $workspace->id) {
            abort(404);
        }

        $galleryIds = Gallery::whereIn('collection_id', $workspace->collections()->pluck('id'))->pluck('id');
        GalleryMember::where('user_id', $member->user_id)->whereIn('gallery_id', $galleryIds)->delete();

        $this->audit->log($request->user(), $workspace, 'workspace.member_removed', [
            'member_id' => $member->id,
            'user_id' => $member->user_id,
        ]);

        $member->delete();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'removed']);
        }

        return redirect()->route('workspaces.members', $workspace);
    }
}
