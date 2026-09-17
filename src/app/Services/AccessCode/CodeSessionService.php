<?php

namespace App\Services\AccessCode;

use App\Models\WorkspaceAccessCode;
use Illuminate\Support\Facades\Crypt;

class CodeSessionService
{
    private const COOKIE_NAME = 'access_code_session';
    private const COOKIE_MINUTES = 60; // session duration

    /**
     * Issue a scoped session token for a validated access code.
     * Returns the signed payload that gets stored as a cookie.
     * raw_code + code_salt are included so the browser can re-derive the
     * code key and unseal the wrapped DEK on subsequent page loads.
     */
    public function issue(WorkspaceAccessCode $code, ?string $rawCode = null): array
    {
        return [
            'code_id' => $code->id,
            'workspace_id' => $code->workspace_id,
            'scope' => $code->scope,
            'scope_id' => $code->scope_id,
            'permissions' => $code->permissions,
            'expires_at' => $code->expires_at->toISOString(),
            'issued_at' => now()->toISOString(),
            'raw_code' => $rawCode,
            'code_salt' => $code->code_salt,
        ];
    }

    /**
     * Encode the payload as an encrypted cookie value.
     */
    public function encode(array $payload): string
    {
        return Crypt::encrypt(json_encode($payload));
    }

    /**
     * Decode a cookie value back to the payload array.
     * Returns null if invalid.
     */
    public function decode(string $encrypted): ?array
    {
        try {
            $decoded = json_decode(Crypt::decrypt($encrypted), true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the cookie name for the session.
     */
    public function cookieName(): string
    {
        return self::COOKIE_NAME;
    }

    /**
     * Get cookie minutes.
     */
    public function cookieMinutes(): int
    {
        return self::COOKIE_MINUTES;
    }
}
