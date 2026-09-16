<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Collection;

class CollectionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Collection $collection): bool
    {
        $workspace = $collection->workspace;

        if ($user->id === $workspace->owner_id) {
            return true;
        }

        if ($workspace->members()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user, Collection $collection): bool
    {
        return $user->id === $collection->workspace->owner_id;
    }

    public function update(User $user, Collection $collection): bool
    {
        return $user->id === $collection->workspace->owner_id;
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $user->id === $collection->workspace->owner_id;
    }
}
