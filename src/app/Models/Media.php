<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory, UsesUuid;

    protected $table = 'media';

    protected $fillable = [
        'gallery_id',
        'uploaded_by',
        'blob_path',
        'thumb_path',
        'cek_wrapped',
        'iv',
        'thumb_iv',
        'mime_type',
        'size',
        'encrypted_title',
        'title_iv',
        'encrypted_caption',
        'caption_iv',
    ];

    protected $hidden = [
        'cek_wrapped',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
