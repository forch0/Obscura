<?php

namespace Tests\Unit\Crypto;

use App\Services\Crypto\KeyDerivationService;
use Tests\TestCase;

class KeyDerivationServiceTest extends TestCase
{
    private KeyDerivationService $kdf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kdf = new KeyDerivationService();
    }

    public function test_pbkdf2_iterations_is_250000_by_default(): void
    {
        $this->assertEquals(250000, $this->kdf->pbkdf2Iterations());
    }

    public function test_pbkdf2_hash_is_sha256(): void
    {
        $this->assertEquals('SHA-256', $this->kdf->pbkdf2Hash());
    }

    public function test_salt_length_is_16_bytes(): void
    {
        $this->assertEquals(16, $this->kdf->saltLength());
    }

    public function test_iv_length_is_12_bytes(): void
    {
        $this->assertEquals(12, $this->kdf->ivLength());
    }

    public function test_generate_salt_returns_base64(): void
    {
        $salt = $this->kdf->generateSalt();

        $this->assertNotEquals($salt, base64_decode($salt));
        $decoded = base64_decode($salt);
        $this->assertEquals(16, strlen($decoded));
    }

    public function test_generate_iv_returns_base64(): void
    {
        $iv = $this->kdf->generateIv();

        $decoded = base64_decode($iv);
        $this->assertEquals(12, strlen($decoded));
    }

    public function test_generated_salts_are_unique(): void
    {
        $salt1 = $this->kdf->generateSalt();
        $salt2 = $this->kdf->generateSalt();

        $this->assertNotEquals($salt1, $salt2);
    }

    public function test_recovery_code_is_24_chars_grouped_in_4s(): void
    {
        $code = $this->kdf->generateRecoveryCode();

        // 24 chars + 5 dashes = 29 chars
        $this->assertEquals(29, strlen($code));
        $parts = explode('-', $code);
        $this->assertCount(6, $parts);
        foreach ($parts as $part) {
            $this->assertEquals(4, strlen($part));
        }
    }

    public function test_recovery_code_uses_safe_charset(): void
    {
        $code = $this->kdf->generateRecoveryCode();

        // Should not contain ambiguous chars: 0, O, 1, I
        $this->assertStringNotContainsString('0', $code);
        $this->assertStringNotContainsString('O', $code);
        $this->assertStringNotContainsString('1', $code);
        $this->assertStringNotContainsString('I', $code);
    }

    public function test_recovery_codes_are_unique(): void
    {
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = $this->kdf->generateRecoveryCode();
        }

        $this->assertEquals(100, count(array_unique($codes)));
    }

    public function test_hash_recovery_code_is_deterministic(): void
    {
        $code = 'TEST-CODE-HERE-NOW';
        $salt = 'test-salt';

        $hash1 = $this->kdf->hashRecoveryCode($code, $salt);
        $hash2 = $this->kdf->hashRecoveryCode($code, $salt);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_hash_recovery_code_differs_with_different_salt(): void
    {
        $code = 'TEST-CODE-HERE-NOW';

        $hash1 = $this->kdf->hashRecoveryCode($code, 'salt1');
        $hash2 = $this->kdf->hashRecoveryCode($code, 'salt2');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_verify_recovery_code_accepts_correct_code(): void
    {
        $code = 'TEST-CODE-HERE-NOW';
        $salt = 'test-salt';
        $hash = $this->kdf->hashRecoveryCode($code, $salt);

        $this->assertTrue($this->kdf->verifyRecoveryCode($code, $salt, $hash));
    }

    public function test_verify_recovery_code_rejects_wrong_code(): void
    {
        $code = 'TEST-CODE-HERE-NOW';
        $salt = 'test-salt';
        $hash = $this->kdf->hashRecoveryCode($code, $salt);

        $this->assertFalse($this->kdf->verifyRecoveryCode('WRONG-CODE-HERE-NOW', $salt, $hash));
    }

    public function test_verify_recovery_code_rejects_wrong_salt(): void
    {
        $code = 'TEST-CODE-HERE-NOW';
        $salt = 'test-salt';
        $hash = $this->kdf->hashRecoveryCode($code, $salt);

        $this->assertFalse($this->kdf->verifyRecoveryCode($code, 'wrong-salt', $hash));
    }
}
