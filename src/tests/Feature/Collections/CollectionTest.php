<?php

namespace Tests\Feature\Collections;

use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\Gallery;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_collection(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->postJson(
            route('collections.store', $workspace),
            [
                'encrypted_name' => base64_encode('encrypted'),
                'name_iv' => base64_encode(random_bytes(12)),
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonStructure(['collection_id']);

        $this->assertDatabaseHas('collections', ['workspace_id' => $workspace->id]);
    }

    public function test_non_owner_cannot_create_collection(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $stranger = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($stranger)->postJson(
            route('collections.store', $workspace),
            [
                'encrypted_name' => base64_encode('encrypted'),
                'name_iv' => base64_encode(random_bytes(12)),
            ]
        );

        $response->assertStatus(403);
    }

    public function test_collection_creation_logs_audit(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson(
            route('collections.store', $workspace),
            [
                'encrypted_name' => base64_encode('encrypted'),
                'name_iv' => base64_encode(random_bytes(12)),
            ]
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.created']);
    }

    public function test_collection_id_is_uuid(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson(
            route('collections.store', $workspace),
            [
                'encrypted_name' => base64_encode('encrypted'),
                'name_iv' => base64_encode(random_bytes(12)),
            ]
        );

        $collection = Collection::first();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $collection->id
        );
    }

    public function test_owner_can_rename_collection(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($owner)->putJson(
            route('collections.update', [$workspace, $collection]),
            [
                'encrypted_name' => base64_encode('new-name'),
                'name_iv' => base64_encode(random_bytes(12)),
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.renamed']);
    }

    public function test_owner_can_delete_collection(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($owner)->deleteJson(
            route('collections.destroy', [$workspace, $collection])
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.deleted']);
    }

    public function test_deleting_collection_cascades_to_galleries(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id]);

        $this->actingAs($owner)->deleteJson(
            route('collections.destroy', [$workspace, $collection])
        );

        $this->assertDatabaseMissing('galleries', ['id' => $gallery->id]);
    }

    public function test_collection_requires_workspace_scoping(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $otherWorkspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $otherWorkspace->id]);

        // Trying to access collection through wrong workspace returns 404
        $response = $this->actingAs($owner)->get(
            route('collections.show', [$workspace, $collection])
        );

        $response->assertStatus(404);
    }
}
