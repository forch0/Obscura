<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
        $response->assertSee('Obscura');
    }

    public function test_new_user_can_register(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertRedirect(route('keygen'));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user->name);
        $this->assertFalse($user->is_super_admin);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'DifferentPassword456!',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_id_is_uuid(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'UUID User',
            'email' => 'uuid@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $user = User::where('email', 'uuid@example.com')->first();
        $this->assertNotNull($user);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $user->id
        );
    }

    public function test_registration_sends_welcome_email(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Email User',
            'email' => 'email@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $user = User::where('email', 'email@example.com')->first();

        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_registered_user_has_null_keypair_fields(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Keypair User',
            'email' => 'keypair@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $user = User::where('email', 'keypair@example.com')->first();
        $this->assertNull($user->public_key);
        $this->assertNull($user->encrypted_private_key);
        $this->assertNull($user->keypair_salt);
        $this->assertFalse($user->hasKeypair());
    }
}
