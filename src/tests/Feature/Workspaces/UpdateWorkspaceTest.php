<?php

namespace Tests\Feature\Workspaces;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rename_workspace(): void
    {
        $user = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->putJson(route('workspaces.update', $workspace), [
            'encrypted_name' => base64_encode('new-encrypted-name'),
            'name_iv' => base64_encode(random_bytes(12)),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'renamed']);

        $workspace->refresh();
        $this->assertNotEquals(Workspace::factory()->newModel()->encrypted_name, $workspace->encrypted_name);
    }

    public function test_rename_logs_audit_entry(): void
    {
        $user = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->putJson(route('workspaces.update', $workspace), [
            'encrypted_name' => base64_encode('new-name'),
            'name_iv' => base64_encode(random_bytes(12)),
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.renamed']);
    }

    public function test_non_owner_cannot_rename(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $stranger = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($stranger)->putJson(route('workspaces.update', $workspace), [
            'encrypted_name' => base64_encode('hacked'),
            'name_iv' => base64_encode(random_bytes(12)),
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_rename_any_workspace(): void
    {
        $admin = User::factory()->withKeypair()->superAdmin()->create();
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($admin)->putJson(route('workspaces.update', $workspace), [
            'encrypted_name' => base64_encode('admin-renamed'),
            'name_iv' => base64_encode(random_bytes(12)),
        ]);

        $response->assertStatus(200);
    }

    public function test_rename_requires_encrypted_name(): void
    {
        $user = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->putJson(route('workspaces.update', $workspace), [
            'name_iv' => base64_encode(random_bytes(12)),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['encrypted_name']);
    }
}
