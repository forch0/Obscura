<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Policies\WorkspacePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePolicyTest extends TestCase
{
    use RefreshDatabase;

    private WorkspacePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new WorkspacePolicy();
    }

    public function test_owner_can_view(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue($this->policy->view($user, $workspace));
    }

    public function test_non_owner_cannot_view(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->assertFalse($this->policy->view($stranger, $workspace));
    }

    public function test_owner_can_update(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $workspace));
    }

    public function test_non_owner_cannot_update(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->assertFalse($this->policy->update($stranger, $workspace));
    }

    public function test_owner_can_delete(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $workspace));
    }

    public function test_non_owner_cannot_delete(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->assertFalse($this->policy->delete($stranger, $workspace));
    }

    public function test_super_admin_bypasses_via_before(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        // before() returns true for super admin — Laravel's Gate calls this first
        $this->assertTrue($this->policy->before($admin, 'view'));
        $this->assertTrue($this->policy->before($admin, 'update'));
        $this->assertTrue($this->policy->before($admin, 'delete'));
    }

    public function test_regular_user_before_returns_null(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        // before() returns null for regular users — falls through to specific checks
        $this->assertNull($this->policy->before($user, 'view'));
    }

    public function test_any_authenticated_user_can_create(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->create($user));
    }
}
