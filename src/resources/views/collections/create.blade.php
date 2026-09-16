@extends('layouts.app')

@section('title', 'New Collection — Obscura')

@section('content')
    <div class="page-header">
        <h2>New Collection</h2>
        <a href="{{ route('collections.index', $workspace) }}" class="btn-secondary">Cancel</a>
    </div>

    <form id="create-collection-form" style="max-width:480px">
        @csrf
        <div class="form-group">
            <label for="name">Collection Name</label>
            <input type="text" id="name" class="form-input" placeholder="Summer 2025" required>
        </div>
        <div class="form-group">
            <label for="description">Description (optional)</label>
            <textarea id="description" class="form-input" rows="3" placeholder="Photos from our trip..."></textarea>
        </div>
        <button type="submit" class="btn-primary" id="submit-btn">Create Collection</button>
        <p class="decrypt-status" id="status"></p>
    </form>
@endsection

@push('scripts')
    <script type="module">
        const form = document.getElementById('create-collection-form');
        const statusEl = document.getElementById('status');
        const submitBtn = document.getElementById('submit-btn');
        const workspaceId = '{{ $workspace->id }}';

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitBtn.disabled = true;
            statusEl.innerHTML = '<span class="spinner"></span> Encrypting…';

            try {
                const name = document.getElementById('name').value;
                const description = document.getElementById('description').value;

                const { encryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
                const { getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

                const dekHandle = getWorkspaceDek(workspaceId);
                if (!dekHandle) throw new Error('Workspace DEK not loaded. Please open the workspace first.');

                const { encryptedName, nameIv } = await encryptName(name, dekHandle);

                let encDesc = null, descIv = null;
                if (description) {
                    const desc = await encryptName(description, dekHandle);
                    encDesc = desc.encryptedName;
                    descIv = desc.nameIv;
                }

                const response = await fetch(`/workspaces/${workspaceId}/collections`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        encrypted_name: encryptedName,
                        name_iv: nameIv,
                        encrypted_description: encDesc,
                        description_iv: descIv,
                    }),
                });

                if (response.ok) {
                    const data = await response.json();
                    window.location.href = `/workspaces/${workspaceId}/collections/${data.collection_id}`;
                } else {
                    throw new Error('Failed to create collection');
                }
            } catch (e) {
                statusEl.textContent = 'Error: ' + e.message;
                submitBtn.disabled = false;
            }
        });
    </script>
@endpush
