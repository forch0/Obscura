<?php

namespace App\Services\Authorization;

use App\Models\Collection;
use App\Models\Gallery;
use App\Models\GalleryMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use Illuminate\Database\Eloquent\Model;

class AuthorizationResolver
{
    /**
     * Resolve access for a given subject. First match wins.
     * Order: SuperAdmin → Owner → Member → AccessCode → deny.
     */
    public function check(User $user, Model $subject, string $ability): bool
    {
        // 1. Super Admin
        if ($user->is_super_admin) {
            return true;
        }

        // 2. Workspace Owner (owns the workspace that the subject belongs to)
        $workspace = $this->workspaceOf($subject);
        if ($workspace && $user->id === $workspace->owner_id) {
            return true;
        }

        // 3. Workspace Member (per workspace role + gallery grants)
        if ($workspace && $this->isWorkspaceMember($user, $workspace)) {
            return $this->checkMemberAbility($user, $subject, $ability);
        }

        // 4. Access Code session (injected by middleware)
        if (request()->has('access_code')) {
            $code = request()->input('access_code');
            if ($code instanceof WorkspaceAccessCode) {
                return $this->checkCodeAbility($code, $subject, $ability);
            }
        }

        return false;
    }

    /**
     * Resolve the workspace that a subject belongs to.
     */
    private function workspaceOf(Model $subject): ?Workspace
    {
        return match (true) {
            $subject instanceof Workspace => $subject,
            $subject instanceof Collection => $subject->workspace,
            $subject instanceof Gallery => $subject->collection->workspace,
            default => null,
        };
    }

    private function isWorkspaceMember(User $user, Workspace $workspace): bool
    {
        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    private function checkMemberAbility(User $user, Model $subject, string $ability): bool
    {
        if ($subject instanceof Gallery) {
            // Private galleries: workspace members can't view
            if ($subject->isPrivate()) {
                return false;
            }

            $member = $subject->members()->where('user_id', $user->id)->first();

            if (!$member) {
                // Not a gallery member — shared/joint: can view only
                return $ability === 'view';
            }

            return match ($ability) {
                'view' => true,
                'upload', 'update', 'delete' => $member->role === GalleryMember::ROLE_EDITOR,
                'comment' => true,
                default => false,
            };
        }

        // Collections: members can view; edit/delete is owner-only
        if ($subject instanceof Collection) {
            return $ability === 'view';
        }

        // Workspace: members can view
        if ($subject instanceof Workspace) {
            return $ability === 'view';
        }

        return false;
    }

    private function checkCodeAbility(WorkspaceAccessCode $code, Model $subject, string $ability): bool
    {
        if (!$this->codeCoversSubject($code, $subject)) {
            return false;
        }

        return match ($ability) {
            'view' => $code->hasPermission(WorkspaceAccessCode::PERMISSION_VIEW),
            'upload' => $code->hasPermission(WorkspaceAccessCode::PERMISSION_UPLOAD),
            'comment' => $code->hasPermission(WorkspaceAccessCode::PERMISSION_COMMENT),
            default => false,
        };
    }

    /**
     * Check if an access code's scope covers the given subject.
     * A workspace-scoped code covers everything in that workspace.
     * A collection-scoped code covers that collection's galleries.
     * A gallery-scoped code covers only that gallery.
     */
    private function codeCoversSubject(WorkspaceAccessCode $code, Model $subject): bool
    {
        return match ($code->scope) {
            'workspace' => $this->workspaceOf($subject)?->id === $code->workspace_id,
            'collection' => match (true) {
                $subject instanceof Collection => $subject->id === $code->scope_id,
                $subject instanceof Gallery => $subject->collection_id === $code->scope_id,
                default => false,
            },
            'gallery' => $subject instanceof Gallery && $subject->id === $code->scope_id,
            default => false,
        };
    }
}
