<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetAlertNotification;
use App\Services\Crypto\KeyDerivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RecoveryCodeController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.recovery-code');
    }

    public function showRecoverForm()
    {
        return view('auth.recover');
    }

    public function recover(Request $request, KeyDerivationService $kdf)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'recovery_code' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->recovery_code_hash) {
            throw ValidationException::withMessages([
                'email' => __('No recovery code set for this account.'),
            ]);
        }

        $storedHash = base64_decode($user->recovery_code_hash);
        $salt = $user->recovery_code_salt;

        if (!$kdf->verifyRecoveryCode($validated['recovery_code'], $salt, $storedHash)) {
            throw ValidationException::withMessages([
                'recovery_code' => __('Invalid recovery code.'),
            ]);
        }

        return response()->json([
            'encrypted_private_key_recovery' => $user->encrypted_private_key_recovery,
            'recovery_code_salt' => $user->recovery_code_salt,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'recovery_code' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'encrypted_private_key' => ['required', 'string'],
            'keypair_salt' => ['required', 'string'],
            'keypair_iv' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        $kdf = app(KeyDerivationService::class);

        if (!$user || !$user->recovery_code_hash) {
            throw ValidationException::withMessages([
                'email' => __('No recovery code set for this account.'),
            ]);
        }

        $storedHash = base64_decode($user->recovery_code_hash);
        $salt = $user->recovery_code_salt;

        if (!$kdf->verifyRecoveryCode($validated['recovery_code'], $salt, $storedHash)) {
            throw ValidationException::withMessages([
                'recovery_code' => __('Invalid recovery code.'),
            ]);
        }

        $user->update([
            'password' => $validated['password'],
            'encrypted_private_key' => $validated['encrypted_private_key'] . ':' . $validated['keypair_iv'],
            'keypair_salt' => $validated['keypair_salt'],
            'recovery_code_used_at' => now(),
        ]);

        $user->notify(new PasswordResetAlertNotification());

        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
