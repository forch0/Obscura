<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Gallery;
use App\Models\GalleryMember;

class GalleryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return null;
    }

    public function view(User $user, Gallery $gallery): bool
    {
        $workspace = $gallery->collection->workspace;

        // Owner always has access
        if ($user->id === $workspace->owner_id) {
            return true;
        }

        // Workspace member check
        $isWorkspaceMember = $workspace->members()->where('user_id', $user->id)->exists();
        if (!$isWorkspaceMember) {
            return false;
        }

        // Private galleries: owner only (handled above)
        if ($gallery->isPrivate()) {
            return false;
        }

        // Shared: any workspace member can view
        if ($gallery->isShared()) {
            return true;
        }

        // Joint: workspace members can view, editors can upload/edit
        if ($gallery->isJoint()) {
            return true; // view is allowed for all workspace members
        }

        return false;
    }

    public function create(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->collection->workspace->owner_id;
    }

    public function update(User $user, Gallery $gallery): bool
    {
        $workspace = $gallery->collection->workspace;

        if ($user->id === $workspace->owner_id) {
            return true;
        }

        // Joint galleries: editor members can update
        if ($gallery->isJoint()) {
            return $gallery->members()
                ->where('user_id', $user->id)
                ->where('role', GalleryMember::ROLE_EDITOR)
                ->exists();
        }

        return false;
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->collection->workspace->owner_id;
    }
}
