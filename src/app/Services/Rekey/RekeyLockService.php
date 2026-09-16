<?php

namespace App\Services\Rekey;

use Illuminate\Support\Facades\Cache;

class RekeyLockService
{
    private const TTL_SECONDS = 1800; // 30 minutes

    public function acquire(string $workspaceId): bool
    {
        return Cache::lock("rekey:ws:{$workspaceId}", self::TTL_SECONDS)->get();
    }

    public function release(string $workspaceId): void
    {
        Cache::lock("rekey:ws:{$workspaceId}")->forceRelease();
    }

    public function isLocked(string $workspaceId): bool
    {
        // Try to acquire — if we can't, it's locked
        $lock = Cache::lock("rekey:ws:{$workspaceId}", self::TTL_SECONDS);
        $acquired = $lock->get();
        if ($acquired) {
            $lock->release(); // we got it — release immediately
            return false;
        }
        return true;
    }
}
