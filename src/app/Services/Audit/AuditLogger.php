<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(Model $actor, Model $subject, string $action, array $context = []): AuditLog
    {
        return AuditLog::create([
            'actor_type' => $actor::class,
            'actor_id' => $actor->id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'action' => $action,
            'context' => $context,
            'ip_address' => Request::ip(),
        ]);
    }
}
