<?php

namespace Tests\Feature\Rekey;

use App\Models\Collection;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\RekeyJob;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekeyTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkspaceWithContent(): array
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id]);
        $media = Media::factory()->create(['gallery_id' => $gallery->id, 'uploaded_by' => $owner->id]);

        return compact('owner', 'workspace', 'collection', 'gallery', 'media');
    }

    public function test_owner_can_initiate_rekey(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspaceWithContent();

        $response = $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));

        $response->assertStatus(200);
        $response->assertJsonStructure(['job_id', 'media', 'collections', 'galleries', 'members', 'owner_public_key']);

        $this->assertDatabaseHas('rekey_jobs', [
            'workspace_id' => $workspace->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_non_owner_cannot_initiate(): void
    {
        ['workspace' => $workspace] = $this->makeWorkspaceWithContent();
        $stranger = User::factory()->withKeypair()->create();

        $response = $this->actingAs($stranger)->postJson(route('workspaces.rekey.initiate', $workspace));

        $response->assertStatus(403);
    }

    public function test_concurrent_rekey_returns_409(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspaceWithContent();

        $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));

        $response = $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));

        $response->assertStatus(409);
    }

    public function test_complete_increments_dek_version(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspaceWithContent();
        $originalVersion = $workspace->dek_version;

        $init = $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));
        $jobId = $init->json('job_id');

        $response = $this->actingAs($owner)->postJson(
            route('workspaces.rekey.complete', [$workspace, $jobId]),
            [
                'new_wrapped_dek_for_owner' => base64_encode(random_bytes(256)),
                'member_wraps' => [],
                'media_wraps' => [],
                'collection_wraps' => [],
                'gallery_wraps' => [],
            ]
        );

        $response->assertStatus(200);
        $this->assertEquals($originalVersion + 1, $workspace->fresh()->dek_version);
        $this->assertNotNull($workspace->fresh()->rekeyed_at);
    }

    public function test_complete_revokes_all_access_codes(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspaceWithContent();

        // Create two access codes
        WorkspaceAccessCode::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        WorkspaceAccessCode::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);

        $init = $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));

        $this->actingAs($owner)->postJson(
            route('workspaces.rekey.complete', [$workspace, $init->json('job_id')]),
            [
                'new_wrapped_dek_for_owner' => base64_encode(random_bytes(256)),
                'member_wraps' => [],
                'media_wraps' => [],
                'collection_wraps' => [],
                'gallery_wraps' => [],
            ]
        );

        $this->assertEquals(0, WorkspaceAccessCode::where('workspace_id', $workspace->id)->whereNull('revoked_at')->count());
    }

    public function test_complete_updates_media_cek_wraps(): void
    {
        ['owner' => $owner, 'workspace' => $workspace, 'media' => $media] = $this->makeWorkspaceWithContent();

        $init = $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));
        $newCek = base64_encode(random_bytes(44));

        $this->actingAs($owner)->postJson(
            route('workspaces.rekey.complete', [$workspace, $init->json('job_id')]),
            [
                'new_wrapped_dek_for_owner' => base64_encode(random_bytes(256)),
                'member_wraps' => [],
                'media_wraps' => [['media_id' => $media->id, 'cek_wrapped' => $newCek]],
                'collection_wraps' => [],
                'gallery_wraps' => [],
            ]
        );

        $this->assertEquals($newCek, $media->fresh()->cek_wrapped);
    }

    public function test_complete_updates_member_wraps(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspaceWithContent();
        $member = User::factory()->withKeypair()->create();
        $wm = WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'editor', 'wrapped_dek' => 'old-wrap']);

        $init = $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));
        $newWrap = base64_encode(random_bytes(256));

        $this->actingAs($owner)->postJson(
            route('workspaces.rekey.complete', [$workspace, $init->json('job_id')]),
            [
                'new_wrapped_dek_for_owner' => base64_encode(random_bytes(256)),
                'member_wraps' => [['member_id' => $wm->id, 'wrapped_dek' => $newWrap]],
                'media_wraps' => [],
                'collection_wraps' => [],
                'gallery_wraps' => [],
            ]
        );

        $this->assertEquals($newWrap, $wm->fresh()->wrapped_dek);
    }

    public function test_complete_logs_audit(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspaceWithContent();

        $init = $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));

        $this->actingAs($owner)->postJson(
            route('workspaces.rekey.complete', [$workspace, $init->json('job_id')]),
            [
                'new_wrapped_dek_for_owner' => base64_encode(random_bytes(256)),
                'member_wraps' => [],
                'media_wraps' => [],
                'collection_wraps' => [],
                'gallery_wraps' => [],
            ]
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.rekeyed']);
    }

    public function test_status_returns_current_job(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspaceWithContent();

        $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));

        $response = $this->actingAs($owner)->getJson(route('workspaces.rekey.status', $workspace));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'in_progress']);
    }

    public function test_complete_rejects_non_in_progress_job(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspaceWithContent();

        $init = $this->actingAs($owner)->postJson(route('workspaces.rekey.initiate', $workspace));
        $jobId = $init->json('job_id');

        // Complete it once
        $this->actingAs($owner)->postJson(
            route('workspaces.rekey.complete', [$workspace, $jobId]),
            [
                'new_wrapped_dek_for_owner' => base64_encode(random_bytes(256)),
                'member_wraps' => [],
                'media_wraps' => [],
                'collection_wraps' => [],
                'gallery_wraps' => [],
            ]
        );

        // Second complete should fail
        $response = $this->actingAs($owner)->postJson(
            route('workspaces.rekey.complete', [$workspace, $jobId]),
            [
                'new_wrapped_dek_for_owner' => base64_encode(random_bytes(256)),
                'member_wraps' => [],
                'media_wraps' => [],
                'collection_wraps' => [],
                'gallery_wraps' => [],
            ]
        );

        $response->assertStatus(422);
    }
}
