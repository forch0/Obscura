@extends('layouts.app')

@section('title', 'New Collection — Obscura')

@section('content')
    <div class="page-header">
        <h2>New Collection</h2>
        <x-button variant="secondary" href="{{ route('collections.index', $workspace) }}">Cancel</x-button>
    </div>

    <div class="card" style="max-width:560px">
        <form id="create-collection-form">
            @csrf
            <x-input label="Collection Name" name="name" placeholder="Summer 2025" required />
            <div class="form-group">
                <label for="description" class="form-label">Description (optional)</label>
                <textarea id="description" name="description" class="form-input" rows="3" placeholder="Photos from our trip..."></textarea>
            </div>
            <x-button type="submit" variant="primary" size="lg" pill class="w-full">Create Collection</x-button>
            <p class="decrypt-status" id="status" style="margin-top:12px"></p>
        </form>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const form = document.getElementById('create-collection-form');
        const statusEl = document.getElementById('status');
        const submitBtn = form.querySelector('button[type=submit]');
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
                if (!dekHandle) throw new Error('Workspace DEK not loaded.');

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
