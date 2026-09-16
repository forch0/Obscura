@extends('layouts.app')

@section('title', 'New Workspace — Obscura')

@section('content')
    <div class="page-header">
        <h2>New Workspace</h2>
        <a href="{{ route('workspaces.index') }}" class="btn-secondary">Cancel</a>
    </div>

    <form id="create-workspace-form" style="max-width:480px">
        @csrf
        <div class="form-group">
            <label for="name">Workspace Name</label>
            <input type="text" id="name" class="form-input" placeholder="My Private Gallery" required>
        </div>
        <button type="submit" class="btn-primary" id="submit-btn">Create Workspace</button>
        <p class="decrypt-status" id="status"></p>
    </form>
@endsection

@push('scripts')
    <script type="module">
        const form = document.getElementById('create-workspace-form');
        const statusEl = document.getElementById('status');
        const submitBtn = document.getElementById('submit-btn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitBtn.disabled = true;
            statusEl.innerHTML = '<span class="spinner"></span> Generating encryption key…';

            try {
                const name = document.getElementById('name').value;
                const { generateAndSealDek, encryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
                const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
                const { setWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

                const privateKeyHandle = getPrivateKeyHandle();
                if (!privateKeyHandle) {
                    throw new Error('Private key not loaded. Please log in again.');
                }

                // Fetch owner's public key from the user model (passed via meta)
                const publicKeyB64 = document.querySelector('meta[name="user-public-key"]').content;

                statusEl.innerHTML = '<span class="spinner"></span> Sealing DEK…';
                const { wrappedDek, dekHandle } = await generateAndSealDek(publicKeyB64);

                statusEl.innerHTML = '<span class="spinner"></span> Encrypting workspace name…';
                const { encryptedName, nameIv } = await encryptName(name, dekHandle);

                statusEl.innerHTML = '<span class="spinner"></span> Creating workspace…';

                const response = await fetch('{{ route("workspaces.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        encrypted_name: encryptedName,
                        name_iv: nameIv,
                        wrapped_dek: wrappedDek,
                        wrapped_dek_iv: '',
                    }),
                });

                if (response.ok) {
                    const data = await response.json();
                    setWorkspaceDek(data.workspace_id, dekHandle);
                    window.location.href = '/workspaces/' + data.workspace_id;
                } else {
                    const err = await response.json();
                    throw new Error(err.message || 'Failed to create workspace');
                }
            } catch (e) {
                statusEl.textContent = 'Error: ' + e.message;
                submitBtn.disabled = false;
            }
        });
    </script>
@endpush
