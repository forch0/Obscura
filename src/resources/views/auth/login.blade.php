@extends('layouts.auth')

@section('title', 'Sign In — Obscura')
@section('card-title', 'Welcome back')
@section('card-subtitle', 'Sign in to your encrypted workspace')

@section('content')
<form method="POST" action="{{ route('login') }}" id="login-form">
    @csrf
    <x-input label="Email" name="email" type="email" :placeholder="'you@example.com'" required value="{{ old('email') }}" autofocus />
    <x-input label="Password" name="password" type="password" placeholder="Your password" required />
    <div class="form-group" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="remember" id="remember" style="width:16px;height:16px">
        <label for="remember" class="text-caption" style="margin:0;cursor:pointer">Remember me</label>
    </div>
    <x-button type="submit" variant="primary" size="lg" pill class="w-full" id="login-btn">Sign In</x-button>
    <p class="decrypt-status" id="login-status" style="margin-top:12px"></p>
</form>
<div style="text-align:center;margin-top:20px;font-size:0.875rem">
    <span class="text-secondary">Don't have an account?</span>
    <a href="{{ route('register') }}" style="color:hsl(var(--foreground));font-weight:500;text-decoration:none">Register</a><br>
    <a href="{{ route('recover') }}" class="text-caption" style="text-decoration:none;display:inline-block;margin-top:8px">Forgot password?</a>
</div>
@endsection

@push('scripts')
<script type="module">
    const form = document.getElementById('login-form');
    const statusEl = document.getElementById('login-status');
    const btn = document.getElementById('login-btn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        btn.disabled = true;
        statusEl.innerHTML = '<span class="spinner"></span> Signing in…';

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const remember = document.getElementById('remember').checked;
        const csrf = document.querySelector('meta[name=csrf-token]').content;

        try {
            // 1. Authenticate with the server
            const res = await fetch('{{ route('login') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ email, password, remember }),
                credentials: 'same-origin',
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Invalid credentials.');
            }

            // 2. Fetch sealed private key + unseal with the password
            statusEl.innerHTML = '<span class="spinner"></span> Unlocking encryption keys…';

            const keyRes = await fetch('{{ route('keypair.show') }}', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            if (keyRes.ok) {
                const { encrypted_private_key, keypair_iv, keypair_salt } = await keyRes.json();
                const { unsealPrivateKey } = await import('{{ Vite::asset("resources/js/crypto/keypair.js") }}');
                const { storePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');

                const key = await unsealPrivateKey(password, encrypted_private_key, keypair_salt, keypair_iv);
                await storePrivateKey(key);
            }
            // If no keypair (keyRes 404), the server will redirect to /keygen.

            // 3. Redirect — the login response's redirect or fallback to /
            const data = await res.json().catch(() => ({}));
            window.location.href = data.redirect || '{{ route('workspaces.index') }}';
        } catch (err) {
            statusEl.textContent = err.message;
            btn.disabled = false;
        }
    });
</script>
@endpush
