<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekeyJob extends Model
{
    use UsesUuid;

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id',
        'initiated_by',
        'total_media',
        'processed_media',
        'status',
        'member_wraps',
        'media_wraps',
        'collection_wraps',
        'gallery_wraps',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'member_wraps' => 'array',
            'media_wraps' => 'array',
            'collection_wraps' => 'array',
            'gallery_wraps' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isStale(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS
            && $this->created_at->lt(now()->subMinutes(30));
    }
}
