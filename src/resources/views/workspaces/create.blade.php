@extends('layouts.app')

@section('title', 'New Workspace — Obscura')

@section('content')
    <div class="page-header">
        <h2>New Workspace</h2>
        <x-button variant="secondary" href="{{ route('workspaces.index') }}">Cancel</x-button>
    </div>

    <div class="card" style="max-width:480px">
        <form id="create-workspace-form">
            @csrf
            <x-input label="Workspace Name" name="name" placeholder="My Gallery" required />
            <x-button type="submit" variant="primary" size="lg" pill class="w-full">Create Workspace</x-button>
            <p class="decrypt-status" id="status" style="margin-top:12px"></p>
        </form>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const form = document.getElementById('create-workspace-form');
        const statusEl = document.getElementById('status');
        const submitBtn = form.querySelector('button[type=submit]');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitBtn.disabled = true;
            statusEl.innerHTML = '<span class="spinner"></span> Generating DEK…';

            try {
                const name = document.getElementById('name').value;
                const { generateAndSealDek, encryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
                const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
                const { setWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

                const privateKeyHandle = getPrivateKeyHandle();
                const publicKeyB64 = document.querySelector('meta[name=user-public-key]').content;
                if (!privateKeyHandle || !publicKeyB64) throw new Error('Keypair not loaded.');

                const { dekHandle, wrappedDek, wrappedDekIv } = await generateAndSealDek(publicKeyB64);
                const { encryptedName, nameIv } = await encryptName(name, dekHandle);

                const response = await fetch('/workspaces', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        encrypted_name: encryptedName,
                        name_iv: nameIv,
                        wrapped_dek: wrappedDek,
                        wrapped_dek_iv: wrappedDekIv,
                    }),
                });

                if (response.ok) {
                    const data = await response.json();
                    setWorkspaceDek(data.workspace_id, dekHandle);
                    window.location.href = `/workspaces/${data.workspace_id}`;
                } else {
                    throw new Error('Failed to create workspace');
                }
            } catch (e) {
                statusEl.textContent = 'Error: ' + e.message;
                submitBtn.disabled = false;
            }
        });
    </script>
@endpush
