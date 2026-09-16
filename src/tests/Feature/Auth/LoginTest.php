<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('Obscura');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'SecurePassword123!',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'login@example.com',
            'password' => 'SecurePassword123!',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'SecurePassword123!',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'login@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'nobody@example.com',
            'password' => 'AnyPassword!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_without_keypair_is_redirected_to_keygen(): void
    {
        $user = User::factory()->create([
            'email' => 'nokey@example.com',
            'password' => 'SecurePassword123!',
        ]);

        // User has no keypair fields set
        $this->assertFalse($user->hasKeypair());

        $response = $this->post(route('login'), [
            'email' => 'nokey@example.com',
            'password' => 'SecurePassword123!',
        ]);

        $response->assertRedirect(route('keygen'));
    }

    public function test_user_with_keypair_is_redirected_home(): void
    {
        $user = User::factory()->withKeypair()->create([
            'email' => 'haskey@example.com',
            'password' => 'SecurePassword123!',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'haskey@example.com',
            'password' => 'SecurePassword123!',
        ]);

        $response->assertRedirect(route('home'));
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
    }
}
