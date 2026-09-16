<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasKeypair
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->hasKeypair()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Keypair not generated.'], 403);
            }

            return redirect()->route('keygen');
        }

        return $next($request);
    }
}
