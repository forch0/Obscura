<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;

class WorkspaceAccessCodePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->owner_id;
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->owner_id;
    }

    public function delete(User $user, WorkspaceAccessCode $code): bool
    {
        return $user->id === $code->workspace->owner_id;
    }
}
