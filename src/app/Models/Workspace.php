<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory, UsesUuid;

    protected $fillable = [
        'owner_id',
        'encrypted_name',
        'name_iv',
        'wrapped_dek_for_owner',
        'wrapped_dek_iv',
        'dek_version',
        'rekeyed_at',
    ];

    protected $hidden = [
        'wrapped_dek_for_owner',
        'wrapped_dek_iv',
    ];

    protected function casts(): array
    {
        return [
            'rekeyed_at' => 'datetime',
            'dek_version' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function accessCodes(): HasMany
    {
        return $this->hasMany(WorkspaceAccessCode::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')->where('subject_type', self::class);
    }

    /**
     * Get the wrapped DEK sealed to the given user's public key.
     * Owner → wrapped_dek_for_owner; member → workspace_members.wrapped_dek.
     */
    public function wrappedDekFor(User $user): ?string
    {
        if ($this->owner_id === $user->id) {
            return $this->wrapped_dek_for_owner;
        }

        return $this->members()->where('user_id', $user->id)->value('wrapped_dek');
    }
}
