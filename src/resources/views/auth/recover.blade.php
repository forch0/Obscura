@extends('layouts.auth')

@section('title', 'Recover — Obscura')
@section('subtitle', 'Reset your password')

@section('content')
<div id="recover-app">
    <!-- Step 1: Enter email + recovery code -->
    <form id="recover-step1" method="POST" @submit.prevent="submitStep1">
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" class="form-input" type="email" name="email" required autofocus placeholder="you@example.com">
        </div>
        <div class="form-group">
            <label for="recovery_code">Recovery Code</label>
            <input id="recovery_code" class="form-input" type="text" name="recovery_code" required placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX" style="font-family:'JetBrains Mono',ui-monospace,monospace;letter-spacing:0.05em;">
        </div>
        <div class="form-group">
            <label for="password">New Password</label>
            <input id="password" class="form-input" type="password" name="password" required placeholder="New password">
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input id="password_confirmation" class="form-input" type="password" name="password_confirmation" required placeholder="Repeat new password">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="loading">
                <span x-show="!loading">Reset Password</span>
                <span x-show="loading" x-cloak>Recovering...</span>
            </button>
        </div>
    </form>
    <div style="text-align:center;margin-top:20px;font-size:0.875rem">
        <a href="{{ route('login') }}">Back to sign in</a>
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
            alert(data.message || 'Invalid recovery code or email.');
            btn.disabled = false;
            btn.textContent = 'Reset Password';
            return;
        }

        const { encrypted_private_key_recovery, recovery_code_salt } = await verifyRes.json();

        // Step 2: Unseal private key with recovery code (browser-side)
        const { unsealPrivateKeyWithRecoveryCode, sealPrivateKeyWithPassword } = await import('{{ asset("js/crypto/recovery.js") }}');

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
            alert(data.message || 'Failed to reset password.');
            btn.disabled = false;
            btn.textContent = 'Reset Password';
        }
    } catch (err) {
        alert('An error occurred during recovery: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Reset Password';
    }
});
</script>
@endpush
