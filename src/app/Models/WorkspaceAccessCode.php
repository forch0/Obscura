<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkspaceAccessCode extends Model
{
    use HasFactory, UsesUuid;

    const PERMISSION_VIEW = 1;
    const PERMISSION_UPLOAD = 2;
    const PERMISSION_COMMENT = 4;

    const SCOPE_WORKSPACE = 'workspace';
    const SCOPE_COLLECTION = 'collection';
    const SCOPE_GALLERY = 'gallery';

    protected $fillable = [
        'workspace_id',
        'scope',
        'scope_id',
        'permissions',
        'code_hash',
        'code_salt',
        'code_prefix',
        'wrapped_dek',
        'expires_at',
        'revoked_at',
        'max_uses',
        'use_count',
        'created_by',
        'label',
        'recipient_email',
    ];

    protected $hidden = [
        'code_hash',
        'code_salt',
        'wrapped_dek',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_uses' => 'integer',
            'use_count' => 'integer',
        ];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasPermission(int $permission): bool
    {
        return ($this->permissions & $permission) === $permission;
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && ($this->max_uses === 0 || $this->use_count < $this->max_uses);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function incrementUseCount(): void
    {
        $this->increment('use_count');
    }

    public function permissionNames(): array
    {
        $names = [];
        if ($this->hasPermission(self::PERMISSION_VIEW)) $names[] = 'view';
        if ($this->hasPermission(self::PERMISSION_UPLOAD)) $names[] = 'upload';
        if ($this->hasPermission(self::PERMISSION_COMMENT)) $names[] = 'comment';
        return $names;
    }
}
