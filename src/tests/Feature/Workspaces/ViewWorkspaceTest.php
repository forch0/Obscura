<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_own_workspace(): void
    {
        $user = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('workspaces.show', $workspace));

        $response->assertStatus(200);
    }

    public function test_stranger_cannot_view_workspace(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $stranger = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($stranger)->get(route('workspaces.show', $workspace));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_any_workspace(): void
    {
        $admin = User::factory()->withKeypair()->superAdmin()->create();
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($admin)->get(route('workspaces.show', $workspace));

        $response->assertStatus(200);
    }

    public function test_guest_redirected_from_view(): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->get(route('workspaces.show', $workspace));

        $response->assertRedirect(route('login'));
    }
}
