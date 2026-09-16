<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryMember extends Model
{
    use HasFactory, UsesUuid;

    const ROLE_EDITOR = 'editor';
    const ROLE_VIEWER = 'viewer';

    protected $fillable = [
        'gallery_id',
        'user_id',
        'role',
    ];

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
