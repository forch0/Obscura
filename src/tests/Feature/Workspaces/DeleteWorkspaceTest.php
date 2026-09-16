<?php

namespace Tests\Feature\Workspaces;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_workspace(): void
    {
        $user = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('workspaces.destroy', $workspace));

        $response->assertRedirect(route('workspaces.index'));
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }

    public function test_delete_logs_audit_entry(): void
    {
        $user = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->delete(route('workspaces.destroy', $workspace));

        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.deleted']);
    }

    public function test_non_owner_cannot_delete(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $stranger = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($stranger)->delete(route('workspaces.destroy', $workspace));

        $response->assertStatus(403);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_super_admin_can_delete_any_workspace(): void
    {
        $admin = User::factory()->withKeypair()->superAdmin()->create();
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($admin)->delete(route('workspaces.destroy', $workspace));

        $response->assertRedirect(route('workspaces.index'));
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }
}
