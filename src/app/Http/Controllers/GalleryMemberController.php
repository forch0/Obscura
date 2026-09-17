<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Gallery;
use App\Models\GalleryMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GalleryMemberController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request, Collection $collection, Gallery $gallery)
    {
        $this->authorize('update', $gallery->collection->workspace);

        $members = $gallery->members()->with('user')->get();

        $eligible = $gallery->collection->workspace->members()
            ->with('user')
            ->whereNotIn('user_id', $members->pluck('user_id'))
            ->get();

        return view('galleries.members', [
            'workspace' => $gallery->collection->workspace,
            'collection' => $gallery->collection,
            'gallery' => $gallery,
            'members' => $members,
            'eligible' => $eligible,
        ]);
    }

    public function store(Request $request, Collection $collection, Gallery $gallery)
    {
        $this->authorize('update', $gallery->collection->workspace);

        if ($gallery->collection_id !== $collection->id) {
            abort(404);
        }

        // Gallery members must be a subset of workspace members
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', Rule::exists('workspace_members', 'user_id')->where('workspace_id', $gallery->collection->workspace_id)],
            'role' => ['required', 'string', 'in:editor,viewer'],
        ]);

        // Private galleries don't accept members
        if ($gallery->isPrivate()) {
            return back()->withErrors(['role' => 'Private galleries cannot have members.']);
        }

        // Shared galleries only accept viewers
        if ($gallery->isShared() && $validated['role'] === 'editor') {
            return back()->withErrors(['role' => 'Shared galleries only accept viewer role.']);
        }

        $member = $gallery->members()->create([
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
        ]);

        $this->audit->log($request->user(), $gallery, 'gallery.member_added', [
            'member_id' => $member->id,
            'role' => $member->role,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['member_id' => $member->id], 201);
        }

        return redirect()->route('galleries.members', [$collection, $gallery]);
    }

    public function update(Request $request, Collection $collection, Gallery $gallery, GalleryMember $member)
    {
        $this->authorize('update', $gallery->collection->workspace);

        if ($member->gallery_id !== $gallery->id) {
            abort(404);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:editor,viewer'],
        ]);

        if ($gallery->isShared() && $validated['role'] === 'editor') {
            return back()->withErrors(['role' => 'Shared galleries only accept viewer role.']);
        }

        $member->update($validated);

        $this->audit->log($request->user(), $gallery, 'gallery.member_role_changed', [
            'member_id' => $member->id,
            'new_role' => $member->role,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'updated']);
        }

        return redirect()->route('galleries.members', [$collection, $gallery]);
    }

    public function destroy(Request $request, Collection $collection, Gallery $gallery, GalleryMember $member)
    {
        $this->authorize('update', $gallery->collection->workspace);

        if ($member->gallery_id !== $gallery->id) {
            abort(404);
        }

        $this->audit->log($request->user(), $gallery, 'gallery.member_removed', [
            'member_id' => $member->id,
            'user_id' => $member->user_id,
        ]);

        $member->delete();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'removed']);
        }

        return redirect()->route('galleries.members', [$collection, $gallery]);
    }
}
