<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_promote_command_promotes_user_to_super_admin(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('admin:promote', ['email' => 'admin@example.com'])
            ->assertSuccessful()
            ->expectsOutput('admin@example.com is now a Super Admin.');

        $user->refresh();
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_promote_command_fails_for_nonexistent_user(): void
    {
        $this->artisan('admin:promote', ['email' => 'nobody@example.com'])
            ->assertFailed()
            ->expectsOutput('No user found with email: nobody@example.com');
    }

    public function test_super_admin_flag_defaults_to_false(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->is_super_admin);
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_factory_can_create_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($user->is_super_admin);
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_super_admin_flag_is_boolean_cast(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertIsBool($user->is_super_admin);
        $this->assertIsBool($user->isSuperAdmin());
    }
}
