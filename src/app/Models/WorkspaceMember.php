<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class WorkspaceMember extends Model
{
    use UsesUuid;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
        'wrapped_dek',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
