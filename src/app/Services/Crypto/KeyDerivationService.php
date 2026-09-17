<?php

namespace App\Services\Crypto;

class KeyDerivationService
{
    public function pbkdf2Iterations(): int
    {
        return config('crypto.pbkdf2.iterations', 250000);
    }

    public function pbkdf2Hash(): string
    {
        return config('crypto.pbkdf2.hash', 'SHA-256');
    }

    public function saltLength(): int
    {
        return config('crypto.pbkdf2.salt_length', 16);
    }

    public function ivLength(): int
    {
        return config('crypto.aes_gcm.iv_length', 12);
    }

    public function generateSalt(): string
    {
        return $this->generateBase64($this->saltLength());
    }

    public function generateIv(): string
    {
        return $this->generateBase64($this->ivLength());
    }

    public function generateBase64(int $bytes): string
    {
        return base64_encode(random_bytes($bytes));
    }

    public function hashRecoveryCode(string $recoveryCode, string $salt): string
    {
        return hash('sha256', $recoveryCode . $salt, true);
    }

    public function verifyRecoveryCode(string $recoveryCode, string $salt, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hashRecoveryCode($recoveryCode, $salt));
    }

    public function generateRecoveryCode(): string
    {
        $charset = config('crypto.recovery_code.charset', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $length = config('crypto.recovery_code.length', 24);
        $groupSize = config('crypto.recovery_code.group_size', 4);

        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $charset[random_int(0, strlen($charset) - 1)];
        }

        return implode('-', str_split($code, $groupSize));
    }
}
