<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gallery extends Model
{
    use HasFactory, UsesUuid;

    const TYPE_PRIVATE = 'private';
    const TYPE_SHARED = 'shared';
    const TYPE_JOINT = 'joint';

    protected $fillable = [
        'collection_id',
        'encrypted_name',
        'name_iv',
        'type',
        'encrypted_description',
        'description_iv',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(GalleryMember::class);
    }

    public function workspace()
    {
        return $this->collection->workspace;
    }

    public function isPrivate(): bool
    {
        return $this->type === self::TYPE_PRIVATE;
    }

    public function isShared(): bool
    {
        return $this->type === self::TYPE_SHARED;
    }

    public function isJoint(): bool
    {
        return $this->type === self::TYPE_JOINT;
    }
}
