<?php

namespace Tests\Feature\Workspaces;

use App\Models\Collection;
use App\Models\Gallery;
use App\Models\GalleryMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceMemberTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkspace(): array
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        return compact('owner', 'workspace');
    }

    public function test_owner_can_view_members_page(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspace();

        $this->actingAs($owner)
            ->get(route('workspaces.members', $workspace))
            ->assertStatus(200);
    }

    public function test_owner_can_add_member(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspace();
        $member = User::factory()->withKeypair()->create();

        $response = $this->actingAs($owner)->postJson(
            route('workspaces.members.store', $workspace),
            ['user_id' => $member->id, 'role' => 'editor', 'wrapped_dek' => 'fake-wrapped-dek']
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'editor',
            'wrapped_dek' => 'fake-wrapped-dek',
        ]);
    }

    public function test_cannot_add_user_without_keypair(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspace();
        $member = User::factory()->create();

        $response = $this->actingAs($owner)->postJson(
            route('workspaces.members.store', $workspace),
            ['user_id' => $member->id, 'role' => 'viewer', 'wrapped_dek' => 'fake-wrapped-dek']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_add_owner_as_member(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspace();

        $response = $this->actingAs($owner)->postJson(
            route('workspaces.members.store', $workspace),
            ['user_id' => $owner->id, 'role' => 'viewer', 'wrapped_dek' => 'fake-wrapped-dek']
        );

        $response->assertSessionHasErrors('user_id');
    }

    public function test_cannot_add_duplicate_member(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspace();
        $member = User::factory()->withKeypair()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'viewer']);

        $response = $this->actingAs($owner)->postJson(
            route('workspaces.members.store', $workspace),
            ['user_id' => $member->id, 'role' => 'editor', 'wrapped_dek' => 'fake-wrapped-dek']
        );

        $response->assertSessionHasErrors('user_id');
    }

    public function test_non_owner_cannot_manage_members(): void
    {
        ['workspace' => $workspace] = $this->makeWorkspace();
        $stranger = User::factory()->withKeypair()->create();
        $member = User::factory()->withKeypair()->create();

        $this->actingAs($stranger)
            ->get(route('workspaces.members', $workspace))
            ->assertStatus(403);

        $this->actingAs($stranger)->postJson(
            route('workspaces.members.store', $workspace),
            ['user_id' => $member->id, 'role' => 'viewer', 'wrapped_dek' => 'fake-wrapped-dek']
        )->assertStatus(403);
    }

    public function test_member_sees_workspace_in_index(): void
    {
        ['workspace' => $workspace] = $this->makeWorkspace();
        $member = User::factory()->withKeypair()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'viewer']);

        $response = $this->actingAs($member)->get(route('workspaces.index'));

        $response->assertStatus(200);
        $response->assertViewHas('workspaces', fn ($workspaces) => $workspaces->contains('id', $workspace->id));
    }

    public function test_removing_member_removes_gallery_memberships(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspace();
        $member = User::factory()->withKeypair()->create();
        $wm = WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'editor']);

        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id, 'type' => 'joint']);
        $gm = GalleryMember::create(['gallery_id' => $gallery->id, 'user_id' => $member->id, 'role' => 'editor']);

        $response = $this->actingAs($owner)->deleteJson(
            route('workspaces.members.destroy', [$workspace, $wm])
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('workspace_members', ['id' => $wm->id]);
        $this->assertDatabaseMissing('gallery_members', ['id' => $gm->id]);
    }

    public function test_member_management_logs_audit(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->makeWorkspace();
        $member = User::factory()->withKeypair()->create();

        $this->actingAs($owner)->postJson(
            route('workspaces.members.store', $workspace),
            ['user_id' => $member->id, 'role' => 'viewer', 'wrapped_dek' => 'fake-wrapped-dek']
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.member_added']);
    }
}
