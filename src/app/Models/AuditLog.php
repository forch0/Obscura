<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use UsesUuid;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'subject_type',
        'subject_id',
        'action',
        'context',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function actor()
    {
        return $this->morphTo('actor');
    }

    public function subject()
    {
        return $this->morphTo('subject');
    }
}
