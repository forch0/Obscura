<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\RecoveryCodeGeneratedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class KeypairTest extends TestCase
{
    use RefreshDatabase;

    public function test_keygen_screen_can_be_rendered_by_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('keygen'));

        $response->assertStatus(200);
        $response->assertSee('encryption keys');
    }

    public function test_keygen_redirects_guests_to_login(): void
    {
        $response = $this->get(route('keygen'));

        $response->assertRedirect(route('login'));
    }

    public function test_keypair_can_be_stored(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('keypair.store'), [
            'public_key' => 'fake-public-key-base64',
            'encrypted_private_key' => 'fake-sealed-private-key',
            'keypair_salt' => base64_encode(random_bytes(16)),
            'keypair_iv' => base64_encode(random_bytes(12)),
            'encrypted_private_key_recovery' => 'fake-recovery-sealed-key',
            'recovery_code_hash' => 'fake-hash-base64',
            'recovery_code_salt' => base64_encode(random_bytes(16)),
            'recovery_iv' => base64_encode(random_bytes(12)),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'stored']);

        $user->refresh();
        $this->assertEquals('fake-public-key-base64', $user->public_key);
        $this->assertNotNull($user->encrypted_private_key);
        $this->assertNotNull($user->keypair_salt);
        $this->assertNotNull($user->keypair_created_at);
        $this->assertNotNull($user->recovery_code_hash);
        $this->assertNotNull($user->recovery_code_salt);
        $this->assertTrue($user->hasKeypair());
    }

    public function test_keypair_store_requires_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('keypair.store'), [
            'public_key' => 'only-this',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'encrypted_private_key',
            'keypair_salt',
            'keypair_iv',
            'encrypted_private_key_recovery',
            'recovery_code_hash',
            'recovery_code_salt',
            'recovery_iv',
        ]);
    }

    public function test_keypair_storage_sends_recovery_code_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('keypair.store'), [
            'public_key' => 'fake-public-key-base64',
            'encrypted_private_key' => 'fake-sealed-private-key',
            'keypair_salt' => base64_encode(random_bytes(16)),
            'keypair_iv' => base64_encode(random_bytes(12)),
            'encrypted_private_key_recovery' => 'fake-recovery-sealed-key',
            'recovery_code_hash' => 'fake-hash-base64',
            'recovery_code_salt' => base64_encode(random_bytes(16)),
            'recovery_iv' => base64_encode(random_bytes(12)),
        ]);

        Notification::assertSentTo($user, RecoveryCodeGeneratedNotification::class);
    }

    public function test_recovery_code_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('recovery-code'));

        $response->assertStatus(200);
        $response->assertSee('recovery code');
    }

    public function test_stored_keypair_hidden_from_serialization(): void
    {
        $user = User::factory()->withKeypair()->create();

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('encrypted_private_key', $serialized);
        $this->assertArrayNotHasKey('encrypted_private_key_recovery', $serialized);
        $this->assertArrayNotHasKey('recovery_code_hash', $serialized);
        $this->assertArrayNotHasKey('recovery_code_salt', $serialized);
    }
}
