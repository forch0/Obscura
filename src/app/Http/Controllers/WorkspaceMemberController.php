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

        return view('workspaces.members', [
            'workspace' => $workspace,
            'members' => $members,
        ]);
    }

    /**
     * Look up a single user by email so the owner can wrap the DEK for them.
     * Deliberately returns generic errors — this must not reveal whether an
     * email is registered unless the lookup is valid for this workspace.
     */
    public function lookup(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->public_key) {
            return response()->json(['error' => 'No account found for that email — they may need to register and generate a keypair first.'], 404);
        }

        if ($user->id === $workspace->owner_id) {
            return response()->json(['error' => 'That is your own account — the owner already has access.'], 422);
        }

        if ($workspace->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'That user is already a member of this workspace.'], 422);
        }

        return response()->json([
            'user_id' => $user->id,
            'public_key' => $user->public_key,
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
