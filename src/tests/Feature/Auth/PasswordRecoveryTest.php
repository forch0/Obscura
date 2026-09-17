<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetAlertNotification;
use App\Services\Crypto\KeyDerivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recover_screen_can_be_rendered(): void
    {
        $response = $this->get(route('recover'));

        $response->assertStatus(200);
        $response->assertSee('Recovery Code');
    }

    public function test_recover_returns_sealed_key_for_valid_code(): void
    {
        $kdf = app(KeyDerivationService::class);

        $recoveryCode = $kdf->generateRecoveryCode();
        $salt = $kdf->generateSalt();

        $user = User::factory()->create([
            'email' => 'recover@example.com',
            'encrypted_private_key_recovery' => 'fake-sealed:fake-iv',
            'recovery_code_hash' => base64_encode($kdf->hashRecoveryCode($recoveryCode, $salt)),
            'recovery_code_salt' => $salt,
        ]);

        $response = $this->postJson(route('recover'), [
            'email' => 'recover@example.com',
            'recovery_code' => $recoveryCode,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'encrypted_private_key_recovery',
            'recovery_code_salt',
        ]);
    }

    public function test_recover_rejects_invalid_code(): void
    {
        $kdf = app(KeyDerivationService::class);

        $salt = $kdf->generateSalt();
        $validCode = $kdf->generateRecoveryCode();

        User::factory()->create([
            'email' => 'recover@example.com',
            'recovery_code_hash' => base64_encode($kdf->hashRecoveryCode($validCode, $salt)),
            'recovery_code_salt' => $salt,
        ]);

        $response = $this->postJson(route('recover'), [
            'email' => 'recover@example.com',
            'recovery_code' => 'WRONG-CODE-HERE-NOW',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recovery_code']);
    }

    public function test_recover_rejects_nonexistent_email(): void
    {
        $response = $this->postJson(route('recover'), [
            'email' => 'nobody@example.com',
            'recovery_code' => 'ANY-CODE-HERE-NOW',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_recover_rejects_user_without_recovery_code(): void
    {
        User::factory()->create([
            'email' => 'norecovery@example.com',
            'recovery_code_hash' => null,
            'recovery_code_salt' => null,
        ]);

        $response = $this->postJson(route('recover'), [
            'email' => 'norecovery@example.com',
            'recovery_code' => 'ANY-CODE-HERE-NOW',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_password_reset_updates_user_and_marks_code_used(): void
    {
        Notification::fake();

        $kdf = app(KeyDerivationService::class);

        $recoveryCode = $kdf->generateRecoveryCode();
        $salt = $kdf->generateSalt();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'OldPassword123!',
            'encrypted_private_key' => 'old-sealed:old-iv',
            'encrypted_private_key_recovery' => 'fake-sealed:fake-iv',
            'recovery_code_hash' => base64_encode($kdf->hashRecoveryCode($recoveryCode, $salt)),
            'recovery_code_salt' => $salt,
        ]);

        $newSalt = base64_encode(random_bytes(16));
        $newIv = base64_encode(random_bytes(12));

        $response = $this->postJson(route('recover.reset'), [
            'email' => 'reset@example.com',
            'recovery_code' => $recoveryCode,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
            'encrypted_private_key' => 'new-sealed-key',
            'keypair_salt' => $newSalt,
            'keypair_iv' => $newIv,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNotEquals('old-sealed:old-iv', $user->encrypted_private_key);
        $this->assertEquals('new-sealed-key:' . $newIv, $user->encrypted_private_key);
        $this->assertEquals($newSalt, $user->keypair_salt);
        $this->assertNotNull($user->recovery_code_used_at);

        Notification::assertSentTo($user, PasswordResetAlertNotification::class);
    }

    public function test_password_reset_rejects_invalid_recovery_code(): void
    {
        $kdf = app(KeyDerivationService::class);

        $recoveryCode = $kdf->generateRecoveryCode();
        $salt = $kdf->generateSalt();

        User::factory()->create([
            'email' => 'reset2@example.com',
            'recovery_code_hash' => base64_encode($kdf->hashRecoveryCode($recoveryCode, $salt)),
            'recovery_code_salt' => $salt,
        ]);

        $response = $this->postJson(route('recover.reset'), [
            'email' => 'reset2@example.com',
            'recovery_code' => 'WRONG-CODE-HERE-NOW',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
            'encrypted_private_key' => 'new-sealed-key',
            'keypair_salt' => base64_encode(random_bytes(16)),
            'keypair_iv' => base64_encode(random_bytes(12)),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recovery_code']);
        $this->assertGuest();
    }
}
