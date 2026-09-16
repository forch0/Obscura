<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListWorkspacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_own_workspaces(): void
    {
        $user = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('workspaces.index'));

        $response->assertStatus(200);
        $response->assertSee('Your Workspaces');
    }

    public function test_owner_does_not_see_others_workspaces(): void
    {
        $owner = User::factory()->withKeypair()->create();
        $stranger = User::factory()->withKeypair()->create();
        Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($stranger)->get(route('workspaces.index'));

        $response->assertStatus(200);
        $this->assertEmpty($response->viewData('workspaces'));
    }

    public function test_super_admin_sees_all_workspaces(): void
    {
        $admin = User::factory()->withKeypair()->superAdmin()->create();
        $owner = User::factory()->withKeypair()->create();
        Workspace::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($admin)->get(route('workspaces.index'));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('workspaces'));
    }

    public function test_guest_redirected_from_index(): void
    {
        $response = $this->get(route('workspaces.index'));

        $response->assertRedirect(route('login'));
    }
}
