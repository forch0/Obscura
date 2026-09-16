<?php

namespace App\Services\Rekey;

use App\Models\Collection;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\RekeyJob;
use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;

class RekeyOrchestrator
{
    public function __construct(private RekeyLockService $locks) {}

    /**
     * Initiate a re-key. Returns the job + all data the browser needs.
     * Throws exception if already in progress.
     */
    public function initiate(Workspace $workspace, string $initiatedBy): array
    {
        if (!$this->locks->acquire($workspace->id)) {
            throw new \RuntimeException('Re-key already in progress.', 409);
        }

        $totalMedia = Media::whereIn('gallery_id',
            $workspace->collections()->with('galleries')->get()->flatMap->galleries->pluck('id')
        )->count();

        $job = RekeyJob::create([
            'workspace_id' => $workspace->id,
            'initiated_by' => $initiatedBy,
            'total_media' => $totalMedia,
            'status' => RekeyJob::STATUS_IN_PROGRESS,
        ]);

        // Gather all data the browser needs for re-wrapping
        $media = Media::whereIn('gallery_id',
            $workspace->collections()->with('galleries')->get()->flatMap->galleries->pluck('id')
        )->get(['id', 'cek_wrapped', 'encrypted_title', 'title_iv', 'encrypted_caption', 'caption_iv']);

        $collections = $workspace->collections()->get(['id', 'encrypted_name', 'name_iv', 'encrypted_description', 'description_iv']);

        $galleries = Gallery::whereIn('collection_id',
            $workspace->collections()->pluck('id')
        )->get(['id', 'encrypted_name', 'name_iv', 'encrypted_description', 'description_iv']);

        $members = $workspace->members()->with('user')->get()->map(fn ($m) => [
            'id' => $m->id,
            'public_key' => $m->user->public_key,
        ]);

        return [
            'job_id' => $job->id,
            'media' => $media,
            'collections' => $collections,
            'galleries' => $galleries,
            'members' => $members,
            'owner_public_key' => $workspace->owner->public_key,
        ];
    }

    /**
     * Apply all re-key wraps in a single transaction.
     */
    public function complete(Workspace $workspace, RekeyJob $job, array $data): int
    {
        $codesRevoked = 0;

        DB::transaction(function () use ($workspace, $job, $data, &$codesRevoked) {
            // 1. Update workspace DEK + version
            $workspace->update([
                'wrapped_dek_for_owner' => $data['new_wrapped_dek_for_owner'],
                'dek_version' => $workspace->dek_version + 1,
                'rekeyed_at' => now(),
            ]);

            // 2. Update each member's wrapped DEK
            foreach ($data['member_wraps'] ?? [] as $wrap) {
                WorkspaceMember::where('id', $wrap['member_id'])
                    ->where('workspace_id', $workspace->id)
                    ->update(['wrapped_dek' => $wrap['wrapped_dek']]);
            }

            // 3. Update media CEKs + re-encrypted text fields
            foreach ($data['media_wraps'] ?? [] as $wrap) {
                Media::where('id', $wrap['media_id'])->update(array_filter([
                    'cek_wrapped' => $wrap['cek_wrapped'] ?? null,
                    'encrypted_title' => $wrap['encrypted_title'] ?? null,
                    'title_iv' => $wrap['title_iv'] ?? null,
                    'encrypted_caption' => $wrap['encrypted_caption'] ?? null,
                    'caption_iv' => $wrap['caption_iv'] ?? null,
                ]));
            }

            // 4. Update collection names/descriptions
            foreach ($data['collection_wraps'] ?? [] as $wrap) {
                Collection::where('id', $wrap['collection_id'])->update(array_filter([
                    'encrypted_name' => $wrap['encrypted_name'] ?? null,
                    'name_iv' => $wrap['name_iv'] ?? null,
                    'encrypted_description' => $wrap['encrypted_description'] ?? null,
                    'description_iv' => $wrap['description_iv'] ?? null,
                ]));
            }

            // 5. Update gallery names/descriptions
            foreach ($data['gallery_wraps'] ?? [] as $wrap) {
                Gallery::where('id', $wrap['gallery_id'])->update(array_filter([
                    'encrypted_name' => $wrap['encrypted_name'] ?? null,
                    'name_iv' => $wrap['name_iv'] ?? null,
                    'encrypted_description' => $wrap['encrypted_description'] ?? null,
                    'description_iv' => $wrap['description_iv'] ?? null,
                ]));
            }

            // 6. Revoke all outstanding access codes
            $codesRevoked = WorkspaceAccessCode::where('workspace_id', $workspace->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            // 7. Mark job complete
            $job->update([
                'status' => RekeyJob::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        });

        // 8. Release lock
        $this->locks->release($workspace->id);

        return $codesRevoked;
    }

    /**
     * Abort a re-key job and release the lock.
     */
    public function abort(Workspace $workspace, RekeyJob $job): void
    {
        $job->update(['status' => RekeyJob::STATUS_FAILED]);
        $this->locks->release($workspace->id);
    }
}
