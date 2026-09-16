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

class GalleryMemberTest extends TestCase
{
    use RefreshDatabase;

    private function makeGalleryWithMember(string $type = 'joint'): array
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id, 'type' => $type]);
        $member = User::factory()->withKeypair()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'editor']);

        return compact('owner', 'workspace', 'collection', 'gallery', 'member');
    }

    public function test_owner_can_add_member_to_joint_gallery(): void
    {
        ['owner' => $owner, 'collection' => $collection, 'gallery' => $gallery, 'member' => $member] = $this->makeGalleryWithMember('joint');

        $response = $this->actingAs($owner)->postJson(
            route('galleries.members.store', [$collection, $gallery]),
            ['user_id' => $member->id, 'role' => 'editor']
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('gallery_members', ['gallery_id' => $gallery->id, 'user_id' => $member->id, 'role' => 'editor']);
    }

    public function test_shared_gallery_only_accepts_viewer(): void
    {
        ['owner' => $owner, 'collection' => $collection, 'gallery' => $gallery, 'member' => $member] = $this->makeGalleryWithMember('shared');

        $response = $this->actingAs($owner)->postJson(
            route('galleries.members.store', [$collection, $gallery]),
            ['user_id' => $member->id, 'role' => 'editor']
        );

        $response->assertSessionHasErrors('role');
    }

    public function test_private_gallery_rejects_members(): void
    {
        ['owner' => $owner, 'collection' => $collection, 'gallery' => $gallery, 'member' => $member] = $this->makeGalleryWithMember('private');

        $response = $this->actingAs($owner)->postJson(
            route('galleries.members.store', [$collection, $gallery]),
            ['user_id' => $member->id, 'role' => 'viewer']
        );

        $response->assertSessionHasErrors('role');
    }

    public function test_member_must_be_workspace_member(): void
    {
        ['owner' => $owner, 'collection' => $collection, 'gallery' => $gallery] = $this->makeGalleryWithMember('joint');
        $nonMember = User::factory()->withKeypair()->create();

        $response = $this->actingAs($owner)->postJson(
            route('galleries.members.store', [$collection, $gallery]),
            ['user_id' => $nonMember->id, 'role' => 'editor']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_owner_can_remove_member(): void
    {
        ['owner' => $owner, 'collection' => $collection, 'gallery' => $gallery, 'member' => $member] = $this->makeGalleryWithMember('joint');
        $gm = GalleryMember::create(['gallery_id' => $gallery->id, 'user_id' => $member->id, 'role' => 'editor']);

        $response = $this->actingAs($owner)->deleteJson(
            route('galleries.members.destroy', [$collection, $gallery, $gm])
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('gallery_members', ['id' => $gm->id]);
    }

    public function test_member_role_can_be_changed(): void
    {
        ['owner' => $owner, 'collection' => $collection, 'gallery' => $gallery, 'member' => $member] = $this->makeGalleryWithMember('joint');
        $gm = GalleryMember::create(['gallery_id' => $gallery->id, 'user_id' => $member->id, 'role' => 'viewer']);

        $response = $this->actingAs($owner)->putJson(
            route('galleries.members.update', [$collection, $gallery, $gm]),
            ['role' => 'editor']
        );

        $response->assertStatus(200);
        $this->assertEquals('editor', $gm->fresh()->role);
    }

    public function test_member_management_logs_audit(): void
    {
        ['owner' => $owner, 'collection' => $collection, 'gallery' => $gallery, 'member' => $member] = $this->makeGalleryWithMember('joint');

        $this->actingAs($owner)->postJson(
            route('galleries.members.store', [$collection, $gallery]),
            ['user_id' => $member->id, 'role' => 'editor']
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'gallery.member_added']);
    }
}
