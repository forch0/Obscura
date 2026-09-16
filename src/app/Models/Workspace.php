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

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')->where('subject_type', self::class);
    }
}
