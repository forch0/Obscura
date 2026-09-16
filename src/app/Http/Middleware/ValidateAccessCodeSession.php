<?php

namespace App\Http\Middleware;

use App\Models\WorkspaceAccessCode;
use App\Services\AccessCode\CodeSessionService;
use Closure;
use Illuminate\Http\Request;

class ValidateAccessCodeSession
{
    public function __construct(private CodeSessionService $sessions) {}

    public function handle(Request $request, Closure $next)
    {
        $token = $request->cookie($this->sessions->cookieName());

        if (!$token) {
            return $next($request); // not a code session; fall through
        }

        $payload = $this->sessions->decode($token);

        if (!$payload || !isset($payload['code_id'])) {
            return $this->deny('Invalid session token.');
        }

        $code = WorkspaceAccessCode::find($payload['code_id']);

        if (!$code) {
            return $this->deny('Access code not found.');
        }

        if ($code->revoked_at) {
            return $this->deny('Access code has been revoked.');
        }

        if ($code->expires_at->isPast()) {
            return $this->deny('Access code has expired.');
        }

        if ($code->max_uses > 0 && $code->use_count >= $code->max_uses) {
            return $this->deny('Access code has reached its use limit.');
        }

        // Attach access code context to request for policies/controllers
        $request->merge(['access_code' => $code, 'access_scope' => $payload]);

        return $next($request);
    }

    private function deny(string $reason)
    {
        if (request()->expectsJson()) {
            return response()->json(['error' => $reason], 403);
        }

        return redirect()->route('enter')->with('error', $reason);
    }
}
