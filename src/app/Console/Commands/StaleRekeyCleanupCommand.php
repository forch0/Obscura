<?php

namespace App\Console\Commands;

use App\Models\RekeyJob;
use App\Services\Rekey\RekeyLockService;
use Illuminate\Console\Command;

class StaleRekeyCleanupCommand extends Command
{
    protected $signature = 'rekey:cleanup';
    protected $description = 'Mark stale re-key jobs as failed and release locks';

    public function handle(RekeyLockService $locks): int
    {
        $stale = RekeyJob::where('status', RekeyJob::STATUS_IN_PROGRESS)
            ->where('created_at', '<', now()->subMinutes(30))
            ->get();

        foreach ($stale as $job) {
            $job->update(['status' => RekeyJob::STATUS_FAILED]);
            $locks->release($job->workspace_id);
            $this->info("Failed stale job {$job->id} for workspace {$job->workspace_id}");
        }

        $this->info("Cleaned up {$stale->count()} stale re-key jobs.");

        return self::SUCCESS;
    }
}
