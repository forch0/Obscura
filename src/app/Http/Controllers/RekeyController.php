<?php

namespace App\Http\Controllers;

use App\Models\RekeyJob;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use App\Services\Rekey\RekeyOrchestrator;
use Illuminate\Http\Request;

class RekeyController extends Controller
{
    public function __construct(
        private RekeyOrchestrator $orchestrator,
        private AuditLogger $audit,
    ) {}

    public function show(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        $activeJob = RekeyJob::where('workspace_id', $workspace->id)
            ->where('status', RekeyJob::STATUS_IN_PROGRESS)
            ->first();

        return view('workspaces.rekey', [
            'workspace' => $workspace,
            'activeJob' => $activeJob,
        ]);
    }

    public function initiate(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        try {
            $data = $this->orchestrator->initiate($workspace, $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json($data);
    }

    public function status(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        $job = RekeyJob::where('workspace_id', $workspace->id)
            ->latest()
            ->first();

        if (!$job) {
            return response()->json(['status' => 'none']);
        }

        return response()->json([
            'job_id' => $job->id,
            'status' => $job->status,
            'total_media' => $job->total_media,
            'processed_media' => $job->processed_media,
            'created_at' => $job->created_at->toISOString(),
        ]);
    }

    public function complete(Request $request, Workspace $workspace, RekeyJob $job)
    {
        $this->authorize('update', $workspace);

        if ($job->workspace_id !== $workspace->id) {
            abort(404);
        }

        if ($job->status !== RekeyJob::STATUS_IN_PROGRESS) {
            return response()->json(['error' => 'Job is not in progress.'], 422);
        }

        $validated = $request->validate([
            'new_wrapped_dek_for_owner' => ['required', 'string'],
            'member_wraps' => ['nullable', 'array'],
            'member_wraps.*.member_id' => ['required', 'uuid'],
            'member_wraps.*.wrapped_dek' => ['required', 'string'],
            'media_wraps' => ['nullable', 'array'],
            'media_wraps.*.media_id' => ['required', 'uuid'],
            'media_wraps.*.cek_wrapped' => ['required', 'string'],
            'collection_wraps' => ['nullable', 'array'],
            'collection_wraps.*.collection_id' => ['required', 'uuid'],
            'gallery_wraps' => ['nullable', 'array'],
            'gallery_wraps.*.gallery_id' => ['required', 'uuid'],
        ]);

        $codesRevoked = $this->orchestrator->complete($workspace, $job, $validated);

        $this->audit->log($request->user(), $workspace, 'workspace.rekeyed', [
            'dek_version' => $workspace->fresh()->dek_version,
            'codes_revoked' => $codesRevoked,
        ]);

        return response()->json([
            'status' => 'completed',
            'dek_version' => $workspace->fresh()->dek_version,
            'codes_revoked' => $codesRevoked,
        ]);
    }
}
