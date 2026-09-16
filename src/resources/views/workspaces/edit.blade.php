@extends('layouts.app')

@section('title', 'Rename Workspace — Obscura')

@section('content')
    <div class="page-header">
        <h2 id="workspace-name"><span class="spinner"></span> Loading…</h2>
        <a href="{{ route('workspaces.show', $workspace) }}" class="btn-secondary">Cancel</a>
    </div>

    <form id="rename-form" style="max-width:480px">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="name">New Workspace Name</label>
            <input type="text" id="name" class="form-input" required>
        </div>
        <button type="submit" class="btn-primary" id="submit-btn">Save</button>
        <p class="decrypt-status" id="status"></p>
    </form>
@endsection

@push('scripts')
    @php
        $wsData = $workspace->only(['id', 'encrypted_name', 'name_iv', 'wrapped_dek_for_owner']);
    @endphp
    <script type="module">
        const workspace = @json($wsData);

        const form = document.getElementById('rename-form');
        const statusEl = document.getElementById('status');
        const submitBtn = document.getElementById('submit-btn');
        const nameInput = document.getElementById('name');

        (async () => {
            const { unsealDek, decryptName, encryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { hasWorkspaceDek, getWorkspaceDek, setWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = getPrivateKeyHandle();
            if (!privateKeyHandle) {
                document.getElementById('workspace-name').textContent = 'Private key not loaded';
                return;
            }

            let dekHandle;
            if (hasWorkspaceDek(workspace.id)) {
                dekHandle = getWorkspaceDek(workspace.id);
            } else {
                dekHandle = await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);
                setWorkspaceDek(workspace.id, dekHandle);
            }

            const name = await decryptName(workspace.encrypted_name, dekHandle, workspace.name_iv);
            document.getElementById('workspace-name').textContent = 'Rename: ' + name;
            nameInput.value = name;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                submitBtn.disabled = true;
                statusEl.innerHTML = '<span class="spinner"></span> Encrypting new name…';

                try {
                    const newName = nameInput.value;
                    const { encryptedName, nameIv } = await encryptName(newName, dekHandle);

                    statusEl.innerHTML = '<span class="spinner"></span> Saving…';

                    const response = await fetch('/workspaces/' + workspace.id, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            encrypted_name: encryptedName,
                            name_iv: nameIv,
                        }),
                    });

                    if (response.ok) {
                        window.location.href = '/workspaces/' + workspace.id;
                    } else {
                        throw new Error('Failed to rename');
                    }
                } catch (e) {
                    statusEl.textContent = 'Error: ' + e.message;
                    submitBtn.disabled = false;
                }
            });
        })();
    </script>
@endpush
