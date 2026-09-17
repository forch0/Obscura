<?php

namespace App\Services\AccessCode;

use Illuminate\Support\Str;

class CodeGeneratorService
{
    private const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // base32, no 0/O/1/I
    private const CODE_LENGTH = 12;
    private const GROUP_SIZE = 4;
    private const PREFIX_LENGTH = 8;

    /**
     * Generate a new access code.
     * Returns ['raw' => 'XXXX-XXXX-XXXX', 'hash' => ..., 'salt' => ..., 'prefix' => ...]
     */
    public function generate(): array
    {
        $rawCode = $this->generateRawCode();
        $salt = $this->generateSalt();
        $hash = $this->hashCode($rawCode, $salt);

        return [
            'raw' => $rawCode,
            'hash' => $hash,
            'salt' => $salt,
            'prefix' => substr($hash, 0, self::PREFIX_LENGTH),
        ];
    }

    /**
     * Generate a 12-char base32 code grouped as XXXX-XXXX-XXXX.
     */
    private function generateRawCode(): string
    {
        $chars = str_split(self::CODE_CHARS);
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, count($chars) - 1)];
            if (($i + 1) % self::GROUP_SIZE === 0 && $i < self::CODE_LENGTH - 1) {
                $code .= '-';
            }
        }

        return $code;
    }

    /**
     * Generate a 16-byte salt, returned as base64.
     */
    public function generateSalt(): string
    {
        return base64_encode(random_bytes(16));
    }

    /**
     * Hash a raw code with Argon2id using the given base64 salt.
     */
    public function hashCode(string $rawCode, string $saltB64): string
    {
        // Normalize: strip dashes, uppercase
        $normalized = strtoupper(str_replace('-', '', $rawCode));

        return password_hash($normalized . ':' . $saltB64, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,   // 64 MB
            'time_cost'   => 4,
            'threads'     => 1,
        ]);
    }

    /**
     * Verify a raw code against a stored Argon2id hash.
     */
    public function verifyCode(string $rawCode, string $saltB64, string $storedHash): bool
    {
        $normalized = strtoupper(str_replace('-', '', $rawCode));

        return password_verify($normalized . ':' . $saltB64, $storedHash);
    }
}
