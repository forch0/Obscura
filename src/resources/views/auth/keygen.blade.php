@extends('layouts.auth')

@section('title', 'Generating Keys — Obscura')
@section('card-title', 'Secure your account')
@section('card-subtitle', 'Generate your encryption keys')

@section('extra-styles')
<style>
    .keygen-form { display: block; }
    .keygen-form.hidden { display: none; }
    .keygen-status { text-align: center; padding: 24px 0; display: none; }
    .keygen-status.active { display: block; }
    .keygen-spinner {
        width: 40px; height: 40px;
        border: 3px solid hsl(var(--border));
        border-top-color: hsl(var(--foreground));
        border-radius: 50%;
        margin: 0 auto 16px;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .keygen-step { font-size: 0.875rem; color: hsl(var(--muted-foreground)); margin-top: 8px; }
    .keygen-done { display: none; }
    .keygen-done.active { display: block; }
    .keygen-error {
        display: none;
        background: hsl(var(--destructive) / 0.08);
        border: 1px solid hsl(var(--destructive) / 0.3);
        color: hsl(var(--destructive));
        padding: 12px 16px;
        border-radius: 8px; font-size: 0.875rem; margin-bottom: 20px;
    }
    .keygen-error.active { display: block; }
    .keygen-info {
        font-size: 0.8125rem; color: hsl(var(--muted-foreground));
        line-height: 1.6; margin-top: 4px; margin-bottom: 24px;
    }
</style>
@endsection

@section('content')
<div id="keygen-app">
    <p class="keygen-info">
        We'll use your password to generate and seal your encryption keys.
        Your password never leaves this browser unencrypted.
    </p>

    <div class="keygen-error" id="keygen-error"></div>

    <form class="keygen-form" id="keygen-form" autocomplete="off">
        <div class="form-group">
            <label for="keygen-password" class="form-label">Your Password</label>
            <input id="keygen-password" class="form-input" type="password" name="password" required autofocus placeholder="Enter your password">
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-full">Generate Keys</button>
    </form>

    <div class="keygen-status" id="keygen-loading">
        <div class="keygen-spinner"></div>
        <div class="keygen-step" id="keygen-step-text">Generating your encryption keys...</div>
    </div>

    <div class="keygen-done" id="keygen-done">
        <p style="text-align:center;color:hsl(var(--foreground));font-weight:500;margin-bottom:12px">
            Keys generated successfully
        </p>
        <p class="keygen-info" style="text-align:center">
            <strong style="color:hsl(var(--foreground))">Important:</strong> Your recovery code is shown on the next page.
            You will need it if you forget your password.
        </p>
        <form method="GET" action="{{ route('recovery-code') }}">
            <button type="submit" class="btn btn-primary btn-lg w-full">View Recovery Code</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script type="module">
document.getElementById('keygen-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const loading = document.getElementById('keygen-loading');
    const stepText = document.getElementById('keygen-step-text');
    const errorEl = document.getElementById('keygen-error');
    const doneEl = document.getElementById('keygen-done');
    const password = this.password.value;

    form.classList.add('hidden');
    loading.classList.add('active');

    try {
        const { generateAndSealKeypair } = await import('{{ Vite::asset("resources/js/crypto/keypair.js") }}');
        const { generateRecoveryCode, sealPrivateKeyWithRecoveryCode } = await import('{{ Vite::asset("resources/js/crypto/recovery.js") }}');

        stepText.textContent = 'Generating RSA keypair...';
        const result = await generateAndSealKeypair(password);

        stepText.textContent = 'Generating recovery code...';
        const recoveryCode = generateRecoveryCode();
        const recovery = await sealPrivateKeyWithRecoveryCode(result.privateKeyPkcs8, recoveryCode);

        stepText.textContent = 'Storing keys on server...';
        const response = await fetch('{{ route("keypair.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                public_key: result.publicKey,
                encrypted_private_key: result.encryptedPrivateKey,
                keypair_salt: result.salt,
                keypair_iv: result.iv,
                encrypted_private_key_recovery: recovery.encryptedPrivateKeyRecovery,
                recovery_code_hash: recovery.recoveryCodeHash,
                recovery_code_salt: recovery.recoveryCodeSalt,
                recovery_iv: recovery.recoveryIv,
            }),
        });

        if (!response.ok) {
            throw new Error('Failed to store keys on server.');
        }

        sessionStorage.setItem('recovery_code', recoveryCode);

        const { storePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
        await storePrivateKey(result.privateKeyHandle);

        loading.classList.remove('active');
        doneEl.classList.add('active');
    } catch (err) {
        loading.classList.remove('active');
        errorEl.classList.add('active');
        errorEl.textContent = 'Error: ' + err.message;
        form.classList.remove('hidden');
    }
});
</script>
@endpush
