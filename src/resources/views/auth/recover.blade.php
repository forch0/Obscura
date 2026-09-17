@extends('layouts.auth')

@section('title', 'Recover — Obscura')
@section('card-title', 'Reset your password')
@section('card-subtitle', 'Use your recovery code to regain access')

@section('content')
<div id="recover-app">
    <!-- Step 1: Enter email + recovery code -->
    <form id="recover-step1" method="POST" @submit.prevent="submitStep1">
        <div class="form-group">
            <label for="email" class="form-label">Email</label>
            <input id="email" class="form-input" type="email" name="email" required autofocus placeholder="you@example.com">
        </div>
        <div class="form-group">
            <label for="recovery_code" class="form-label">Recovery Code</label>
            <input id="recovery_code" class="form-input mono" type="text" name="recovery_code" required placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX" style="letter-spacing:0.05em">
        </div>
        <div class="form-group">
            <label for="password" class="form-label">New Password</label>
            <input id="password" class="form-input" type="password" name="password" required placeholder="New password">
        </div>
        <div class="form-group">
            <label for="password_confirmation" class="form-label">Confirm New Password</label>
            <input id="password_confirmation" class="form-input" type="password" name="password_confirmation" required placeholder="Repeat new password">
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-full" id="recover-btn">Reset Password</button>
    </form>
    <div style="text-align:center;margin-top:20px;font-size:0.875rem">
        <a href="{{ route('login') }}" class="text-secondary" style="text-decoration:none">Back to sign in</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('recover-step1').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Recovering...';

    const email = this.email.value;
    const recoveryCode = this.recovery_code.value;
    const password = this.password.value;
    const passwordConfirmation = this.password_confirmation.value;

    try {
        // Step 1: Verify recovery code and get sealed private key
        const verifyRes = await fetch('{{ route("recover") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ email, recovery_code, password, password_confirmation: passwordConfirmation }),
        });

        if (!verifyRes.ok) {
            const data = await verifyRes.json();
            await ObscuraDialog.alertDialog({ title: 'Recovery failed', message: data.message || 'Invalid recovery code or email.' });
            btn.disabled = false;
            btn.textContent = 'Reset Password';
            return;
        }

        const { encrypted_private_key_recovery, recovery_code_salt } = await verifyRes.json();

        // Step 2: Unseal private key with recovery code (browser-side)
        const { unsealPrivateKeyWithRecoveryCode, sealPrivateKeyWithPassword } = await import('{{ Vite::asset("resources/js/crypto/recovery.js") }}');

        const [sealedB64, iv] = encrypted_private_key_recovery.split(':');
        const privPkcs8 = await unsealPrivateKeyWithRecoveryCode(recoveryCode, sealedB64, recovery_code_salt, iv);

        // Step 3: Re-seal with new password
        const { sealedPriv, salt, iv: newIv } = await sealPrivateKeyWithPassword(privPkcs8, password);

        // Step 4: Submit to server
        const resetRes = await fetch('{{ route("recover.reset") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                email,
                recovery_code: recoveryCode,
                password,
                password_confirmation: passwordConfirmation,
                encrypted_private_key: sealedPriv,
                keypair_salt: salt,
                keypair_iv: newIv,
            }),
        });

        if (resetRes.ok) {
            window.location.href = '{{ route("home") }}';
        } else {
            const data = await resetRes.json();
            await ObscuraDialog.alertDialog({ title: 'Reset failed', message: data.message || 'Failed to reset password.' });
            btn.disabled = false;
            btn.textContent = 'Reset Password';
        }
    } catch (err) {
        await ObscuraDialog.alertDialog({ title: 'Error', message: 'An error occurred during recovery: ' + err.message });
        btn.disabled = false;
        btn.textContent = 'Reset Password';
    }
});
</script>
@endpush
