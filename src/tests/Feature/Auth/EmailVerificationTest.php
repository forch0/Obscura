<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_notice_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertStatus(200);
        $response->assertSee('verification');
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $hash = sha1($user->email);

        $response = $this->actingAs($user)->get(
            route('verification.verify', ['id' => $user->id, 'hash' => $hash])
        );

        $response->assertRedirect(route('home'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_already_verified_redirects_home(): void
    {
        $user = User::factory()->create();

        $hash = sha1($user->email);

        $response = $this->actingAs($user)->get(
            route('verification.verify', ['id' => $user->id, 'hash' => $hash])
        );

        $response->assertRedirect(route('home'));
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->post(route('verification.send'));

        $response->assertRedirect();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verified_user_cannot_resent(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('verification.send'));

        $response->assertRedirect(route('home'));
    }

    public function test_registration_triggers_verification_email(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Verify User',
            'email' => 'verify@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $user = User::where('email', 'verify@example.com')->first();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
