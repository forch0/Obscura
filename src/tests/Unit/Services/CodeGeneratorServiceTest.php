<?php

namespace Tests\Unit\Services;

use App\Services\AccessCode\CodeGeneratorService;
use App\Services\AccessCode\CodeSessionService;
use App\Models\WorkspaceAccessCode;
use Tests\TestCase;

class CodeGeneratorServiceTest extends TestCase
{
    private CodeGeneratorService $codes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codes = new CodeGeneratorService();
    }

    public function test_generates_12_char_code_with_dashes(): void
    {
        $result = $this->codes->generate();

        $this->assertMatchesRegularExpression(
            '/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/',
            $result['raw']
        );
    }

    public function test_codes_are_unique(): void
    {
        $codes = [];
        for ($i = 0; $i < 50; $i++) {
            $codes[] = $this->codes->generate()['raw'];
        }
        $this->assertEquals(50, count(array_unique($codes)));
    }

    public function test_code_uses_safe_charset(): void
    {
        $result = $this->codes->generate();
        $clean = str_replace('-', '', $result['raw']);

        $this->assertStringNotContainsString('0', $clean);
        $this->assertStringNotContainsString('O', $clean);
        $this->assertStringNotContainsString('1', $clean);
        $this->assertStringNotContainsString('I', $clean);
    }

    public function test_salt_is_base64_16_bytes(): void
    {
        $salt = $this->codes->generateSalt();
        $decoded = base64_decode($salt);
        $this->assertEquals(16, strlen($decoded));
    }

    public function test_hash_is_argon2id(): void
    {
        $result = $this->codes->generate();
        $this->assertStringStartsWith('$argon2id', $result['hash']);
    }

    public function test_verify_accepts_correct_code(): void
    {
        $result = $this->codes->generate();
        $this->assertTrue(
            $this->codes->verifyCode($result['raw'], $result['salt'], $result['hash'])
        );
    }

    public function test_verify_rejects_wrong_code(): void
    {
        $result = $this->codes->generate();
        $this->assertFalse(
            $this->codes->verifyCode('XXXX-XXXX-XXXX', $result['salt'], $result['hash'])
        );
    }

    public function test_verify_rejects_wrong_salt(): void
    {
        $result = $this->codes->generate();
        $otherSalt = $this->codes->generateSalt();
        $this->assertFalse(
            $this->codes->verifyCode($result['raw'], $otherSalt, $result['hash'])
        );
    }

    public function test_verify_normalizes_dashes_and_case(): void
    {
        $result = $this->codes->generate();
        $lowerNoDashes = strtolower(str_replace('-', '', $result['raw']));
        $this->assertTrue(
            $this->codes->verifyCode($lowerNoDashes, $result['salt'], $result['hash'])
        );
    }
}
