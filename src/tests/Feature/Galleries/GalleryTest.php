<?php

namespace Tests\Feature\Galleries;

use App\Models\Collection;
use App\Models\Gallery;
use App\Models\GalleryMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    private function makeGallery(string $type = 'private'): array
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id, 'type' => $type]);

        return compact('owner', 'workspace', 'collection', 'gallery');
    }

    public function test_owner_can_create_gallery(): void
    {
        ['owner' => $owner, 'workspace' => $workspace, 'collection' => $collection] = $this->makeGallery();

        $response = $this->actingAs($owner)->postJson(
            route('galleries.store', $collection),
            [
                'encrypted_name' => base64_encode('encrypted'),
                'name_iv' => base64_encode(random_bytes(12)),
                'type' => 'private',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('galleries', ['collection_id' => $collection->id, 'type' => 'private']);
    }

    public function test_owner_can_create_shared_gallery(): void
    {
        ['owner' => $owner, 'collection' => $collection] = $this->makeGallery();

        $response = $this->actingAs($owner)->postJson(
            route('galleries.store', $collection),
            [
                'encrypted_name' => base64_encode('encrypted'),
                'name_iv' => base64_encode(random_bytes(12)),
                'type' => 'shared',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('galleries', ['type' => 'shared']);
    }

    public function test_owner_can_create_joint_gallery(): void
    {
        ['owner' => $owner, 'collection' => $collection] = $this->makeGallery();

        $response = $this->actingAs($owner)->postJson(
            route('galleries.store', $collection),
            [
                'encrypted_name' => base64_encode('encrypted'),
                'name_iv' => base64_encode(random_bytes(12)),
                'type' => 'joint',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('galleries', ['type' => 'joint']);
    }

    public function test_private_gallery_owner_only(): void
    {
        ['owner' => $owner, 'gallery' => $gallery, 'collection' => $collection] = $this->makeGallery('private');

        // Owner can view
        $response = $this->actingAs($owner)->get(route('galleries.show', [$collection, $gallery]));
        $response->assertStatus(200);

        // Stranger cannot
        $stranger = User::factory()->withKeypair()->create();
        $response = $this->actingAs($stranger)->get(route('galleries.show', [$collection, $gallery]));
        $response->assertStatus(403);
    }

    public function test_shared_gallery_workspace_member_can_view(): void
    {
        ['owner' => $owner, 'workspace' => $workspace, 'collection' => $collection, 'gallery' => $gallery] = $this->makeGallery('shared');

        $member = User::factory()->withKeypair()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'viewer']);

        $response = $this->actingAs($member)->get(route('galleries.show', [$collection, $gallery]));
        $response->assertStatus(200);
    }

    public function test_joint_gallery_editor_can_view(): void
    {
        ['owner' => $owner, 'workspace' => $workspace, 'collection' => $collection, 'gallery' => $gallery] = $this->makeGallery('joint');

        $editor = User::factory()->withKeypair()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $editor->id, 'role' => 'editor']);
        GalleryMember::create(['gallery_id' => $gallery->id, 'user_id' => $editor->id, 'role' => 'editor']);

        $response = $this->actingAs($editor)->get(route('galleries.show', [$collection, $gallery]));
        $response->assertStatus(200);
    }

    public function test_gallery_scoping_checks_collection(): void
    {
        ['owner' => $owner, 'collection' => $collection] = $this->makeGallery();
        $otherCollection = Collection::factory()->create(['workspace_id' => $collection->workspace_id]);
        $gallery = Gallery::factory()->create(['collection_id' => $otherCollection->id]);

        // Trying to access gallery through wrong collection returns 404
        $response = $this->actingAs($owner)->get(route('galleries.show', [$collection, $gallery]));
        $response->assertStatus(404);
    }

    public function test_owner_can_delete_gallery(): void
    {
        ['owner' => $owner, 'collection' => $collection, 'gallery' => $gallery] = $this->makeGallery();

        $response = $this->actingAs($owner)->deleteJson(
            route('galleries.destroy', [$collection, $gallery])
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('galleries', ['id' => $gallery->id]);
    }
}
