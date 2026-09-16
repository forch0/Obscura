@extends('layouts.app')

@section('title', 'Rename Collection — Obscura')

@section('content')
    <div class="page-header">
        <h2>Rename Collection</h2>
        <x-button variant="secondary" href="{{ route('collections.show', [$workspace, $collection]) }}">Cancel</x-button>
    </div>

    <div class="card" style="max-width:560px">
        <form id="rename-collection-form">
            @csrf
            <x-input label="New Name" name="name" required />
            <div class="form-group">
                <label for="description" class="form-label">Description (optional)</label>
                <textarea id="description" class="form-input" rows="3"></textarea>
            </div>
            <x-button type="submit" variant="primary" size="lg" pill class="w-full">Rename</x-button>
            <p class="decrypt-status" id="status" style="margin-top:12px"></p>
        </form>
    </div>
@endsection

@push('scripts')
    @php
        $wsData = $workspace->only(['id', 'wrapped_dek_for_owner']);
        $collData = $collection->only(['id', 'encrypted_name', 'name_iv', 'encrypted_description', 'description_iv']);
    @endphp
    <script type="module">
        const workspace = @json($wsData);
        const collection = @json($collData);
        const form = document.getElementById('rename-collection-form');
        const statusEl = document.getElementById('status');
        const submitBtn = form.querySelector('button[type=submit]');
        const nameInput = document.getElementById('name');
        const descInput = document.getElementById('description');

        (async () => {
            const { unsealDek, decryptName, encryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = getPrivateKeyHandle();
            if (!privateKeyHandle) { statusEl.textContent = 'Private key not loaded.'; return; }

            const dekHandle = getWorkspaceDek(workspace.id)
                || await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);

            try {
                nameInput.value = await decryptName(collection.encrypted_name, dekHandle, collection.name_iv);
                if (collection.encrypted_description) {
                    descInput.value = await decryptName(collection.encrypted_description, dekHandle, collection.description_iv);
                }
            } catch (e) { nameInput.placeholder = 'Unable to decrypt'; }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                submitBtn.disabled = true;
                statusEl.innerHTML = '<span class="spinner"></span> Encrypting…';

                const { encryptedName, nameIv } = await encryptName(nameInput.value, dekHandle);
                let encDesc = null, descIv = null;
                if (descInput.value) {
                    const d = await encryptName(descInput.value, dekHandle);
                    encDesc = d.encryptedName; descIv = d.nameIv;
                }

                const res = await fetch(`/workspaces/${workspace.id}/collections/${collection.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ encrypted_name: encryptedName, name_iv: nameIv, encrypted_description: encDesc, description_iv: descIv }),
                });

                if (res.ok) window.location.href = `/workspaces/${workspace.id}/collections/${collection.id}`;
                else { statusEl.textContent = 'Failed to rename.'; submitBtn.disabled = false; }
            });
        })();
    </script>
@endpush
