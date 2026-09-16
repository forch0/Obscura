@extends('layouts.app')

@section('title', 'Rename Workspace — Obscura')

@section('content')
    <div class="page-header">
        <h2>Rename Workspace</h2>
        <x-button variant="secondary" href="{{ route('workspaces.show', $workspace) }}">Cancel</x-button>
    </div>

    <div class="card" style="max-width:480px">
        <form id="rename-workspace-form">
            @csrf
            <x-input label="New Name" name="name" required />
            <x-button type="submit" variant="primary" size="lg" pill class="w-full">Rename</x-button>
            <p class="decrypt-status" id="status" style="margin-top:12px"></p>
        </form>
    </div>
@endsection

@push('scripts')
    @php
        $wsData = $workspace->only(['id', 'encrypted_name', 'name_iv', 'wrapped_dek_for_owner']);
    @endphp
    <script type="module">
        const workspace = @json($wsData);
        const form = document.getElementById('rename-workspace-form');
        const statusEl = document.getElementById('status');
        const submitBtn = form.querySelector('button[type=submit]');
        const nameInput = document.getElementById('name');

        (async () => {
            const { unsealDek, decryptName, encryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = await restorePrivateKey();
            if (!privateKeyHandle) { statusEl.textContent = 'Private key not loaded.'; return; }

            const dekHandle = getWorkspaceDek(workspace.id)
                || await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);

            try {
                nameInput.value = await decryptName(workspace.encrypted_name, dekHandle, workspace.name_iv);
            } catch (e) { nameInput.placeholder = 'Unable to decrypt'; }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                submitBtn.disabled = true;
                statusEl.innerHTML = '<span class="spinner"></span> Encrypting…';

                const { encryptedName, nameIv } = await encryptName(nameInput.value, dekHandle);

                const res = await fetch(`/workspaces/${workspace.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ encrypted_name: encryptedName, name_iv: nameIv }),
                });

                if (res.ok) window.location.href = `/workspaces/${workspace.id}`;
                else { statusEl.textContent = 'Failed to rename.'; submitBtn.disabled = false; }
            });
        })();
    </script>
@endpush
