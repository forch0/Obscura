<?php

namespace Tests\Feature\AccessCodes;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevokeCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_revoke_code(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $code = WorkspaceAccessCode::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);

        $response = $this->actingAs($owner)->deleteJson(
            route('access-codes.revoke', [$workspace, $code])
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'revoked']);

        $code->refresh();
        $this->assertNotNull($code->revoked_at);
    }

    public function test_non_owner_cannot_revoke(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $stranger = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $code = WorkspaceAccessCode::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);

        $response = $this->actingAs($stranger)->deleteJson(
            route('access-codes.revoke', [$workspace, $code])
        );

        $response->assertStatus(403);
    }

    public function test_revoke_logs_audit(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $code = WorkspaceAccessCode::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);

        $this->actingAs($owner)->deleteJson(
            route('access-codes.revoke', [$workspace, $code])
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'access_code.revoked']);
    }

    public function test_revoked_code_cannot_be_used(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $code = WorkspaceAccessCode::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $code->revoke();

        $this->assertTrue($code->isRevoked());
        $this->assertFalse($code->isActive());
    }
}
