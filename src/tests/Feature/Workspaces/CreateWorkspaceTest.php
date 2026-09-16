<?php

namespace Tests\Feature\Workspaces;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_screen_can_be_rendered(): void
    {
        $user = User::factory()->withKeypair()->create();

        $response = $this->actingAs($user)->get(route('workspaces.create'));

        $response->assertStatus(200);
        $response->assertSee('New Workspace');
    }

    public function test_guest_cannot_access_create(): void
    {
        $response = $this->get(route('workspaces.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_create_workspace(): void
    {
        $user = User::factory()->withKeypair()->create();

        $response = $this->actingAs($user)->postJson(route('workspaces.store'), [
            'encrypted_name' => base64_encode('encrypted-name'),
            'name_iv' => base64_encode(random_bytes(12)),
            'wrapped_dek' => base64_encode(random_bytes(256)),
            'wrapped_dek_iv' => '',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['workspace_id']);

        $workspace = Workspace::first();
        $this->assertNotNull($workspace);
        $this->assertEquals($user->id, $workspace->owner_id);
        $this->assertEquals(1, $workspace->dek_version);
        $this->assertNotNull($workspace->wrapped_dek_for_owner);
    }

    public function test_create_logs_audit_entries(): void
    {
        $user = User::factory()->withKeypair()->create();

        $this->actingAs($user)->postJson(route('workspaces.store'), [
            'encrypted_name' => base64_encode('encrypted'),
            'name_iv' => base64_encode(random_bytes(12)),
            'wrapped_dek' => base64_encode(random_bytes(256)),
            'wrapped_dek_iv' => '',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.dek_stored']);
        $this->assertEquals(2, AuditLog::count());
    }

    public function test_create_requires_encrypted_name(): void
    {
        $user = User::factory()->withKeypair()->create();

        $response = $this->actingAs($user)->postJson(route('workspaces.store'), [
            'name_iv' => base64_encode(random_bytes(12)),
            'wrapped_dek' => base64_encode(random_bytes(256)),
            'wrapped_dek_iv' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['encrypted_name']);
    }

    public function test_create_requires_wrapped_dek(): void
    {
        $user = User::factory()->withKeypair()->create();

        $response = $this->actingAs($user)->postJson(route('workspaces.store'), [
            'encrypted_name' => base64_encode('encrypted'),
            'name_iv' => base64_encode(random_bytes(12)),
            'wrapped_dek_iv' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['wrapped_dek']);
    }

    public function test_workspace_id_is_uuid(): void
    {
        $user = User::factory()->withKeypair()->create();

        $this->actingAs($user)->postJson(route('workspaces.store'), [
            'encrypted_name' => base64_encode('encrypted'),
            'name_iv' => base64_encode(random_bytes(12)),
            'wrapped_dek' => base64_encode(random_bytes(256)),
            'wrapped_dek_iv' => '',
        ]);

        $workspace = Workspace::first();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $workspace->id
        );
    }
}
