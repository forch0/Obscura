<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\User;
use App\Services\Authorization\AuthorizationResolver;

class MediaPolicy
{
    /**
     * Super Admin CANNOT view media — they have no DEK.
     * This is the one place where super admin is explicitly denied (defense in depth).
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_super_admin) {
            return false;
        }

        return null;
    }

    public function view(User $user, Media $media): bool
    {
        return app(AuthorizationResolver::class)->check($user, $media->gallery, 'view');
    }

    public function create(User $user, Gallery $gallery): bool
    {
        return app(AuthorizationResolver::class)->check($user, $gallery, 'upload');
    }

    public function update(User $user, Media $media): bool
    {
        return app(AuthorizationResolver::class)->check($user, $media->gallery, 'upload');
    }

    public function delete(User $user, Media $media): bool
    {
        return app(AuthorizationResolver::class)->check($user, $media->gallery, 'upload');
    }
}
