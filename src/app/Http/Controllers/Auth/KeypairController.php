<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\RecoveryCodeGeneratedNotification;
use Illuminate\Http\Request;

class KeypairController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user->hasKeypair()) {
            return response()->json(['error' => 'No keypair.'], 404);
        }

        // encrypted_private_key is stored as "sealed:iv"
        [$sealed, $iv] = explode(':', $user->encrypted_private_key);

        return response()->json([
            'encrypted_private_key' => $sealed,
            'keypair_iv' => $iv,
            'keypair_salt' => $user->keypair_salt,
            'public_key' => $user->public_key,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'public_key' => ['required', 'string'],
            'encrypted_private_key' => ['required', 'string'],
            'keypair_salt' => ['required', 'string'],
            'keypair_iv' => ['required', 'string'],
            'encrypted_private_key_recovery' => ['required', 'string'],
            'recovery_code_hash' => ['required', 'string'],
            'recovery_code_salt' => ['required', 'string'],
            'recovery_iv' => ['required', 'string'],
        ]);

        $user = $request->user();

        $user->update([
            'public_key' => $validated['public_key'],
            'encrypted_private_key' => $validated['encrypted_private_key'] . ':' . $validated['keypair_iv'],
            'encrypted_private_key_recovery' => $validated['encrypted_private_key_recovery'] . ':' . $validated['recovery_iv'],
            'recovery_code_hash' => $validated['recovery_code_hash'],
            'recovery_code_salt' => $validated['recovery_code_salt'],
            'keypair_salt' => $validated['keypair_salt'],
            'keypair_created_at' => now(),
        ]);

        $user->notify(new RecoveryCodeGeneratedNotification());

        return response()->json(['status' => 'stored']);
    }
}
