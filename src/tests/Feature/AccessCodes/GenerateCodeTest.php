<?php

namespace Tests\Feature\AccessCodes;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_generate_access_code(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->postJson(
            route('access-codes.store', $workspace),
            [
                'scope' => 'workspace',
                'permissions' => 1,
                'duration_minutes' => 60,
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonStructure(['code_id', 'raw_code', 'code_salt']);

        $code = WorkspaceAccessCode::first();
        $this->assertNotNull($code);
        $this->assertEquals('workspace', $code->scope);
        $this->assertEquals(1, $code->permissions);
        $this->assertTrue($code->hasPermission(WorkspaceAccessCode::PERMISSION_VIEW));
        $this->assertFalse($code->hasPermission(WorkspaceAccessCode::PERMISSION_UPLOAD));
    }

    public function test_raw_code_is_returned_once(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->postJson(
            route('access-codes.store', $workspace),
            [
                'scope' => 'workspace',
                'permissions' => 1,
                'duration_minutes' => 60,
            ]
        );

        $rawCode = $response->json('raw_code');
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $rawCode);

        // Raw code is NOT in the database
        $code = WorkspaceAccessCode::first();
        $this->assertNull($code->wrapped_dek); // not yet sealed
        $this->assertNotEquals($rawCode, $code->code_hash); // hash is stored, not plaintext
    }

    public function test_non_owner_cannot_generate_code(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $stranger = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($stranger)->postJson(
            route('access-codes.store', $workspace),
            [
                'scope' => 'workspace',
                'permissions' => 1,
                'duration_minutes' => 60,
            ]
        );

        $response->assertStatus(403);
    }

    public function test_code_generation_logs_audit(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson(
            route('access-codes.store', $workspace),
            [
                'scope' => 'workspace',
                'permissions' => 1,
                'duration_minutes' => 60,
            ]
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'access_code.generated']);
    }

    public function test_code_requires_permissions(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->postJson(
            route('access-codes.store', $workspace),
            [
                'scope' => 'workspace',
                'duration_minutes' => 60,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['permissions']);
    }
}
