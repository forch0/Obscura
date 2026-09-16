@extends('layouts.app')

@section('title', 'New Gallery — Obscura')

@section('content')
    <div class="page-header">
        <h2>New Gallery</h2>
        <a href="{{ route('collections.show', [$workspace, $collection]) }}" class="btn-secondary">Cancel</a>
    </div>

    <form id="create-gallery-form" style="max-width:480px">
        @csrf
        <div class="form-group">
            <label for="name">Gallery Name</label>
            <input type="text" id="name" class="form-input" placeholder="Beach Photos" required>
        </div>
        <div class="form-group">
            <label>Type</label>
            <select id="type" class="form-input">
                <option value="private">Private — owner only</option>
                <option value="shared">Shared — workspace members can view</option>
                <option value="joint">Joint — editor members can upload/edit</option>
            </select>
        </div>
        <div class="form-group">
            <label for="description">Description (optional)</label>
            <textarea id="description" class="form-input" rows="3"></textarea>
        </div>
        <button type="submit" class="btn-primary" id="submit-btn">Create Gallery</button>
        <p class="decrypt-status" id="status"></p>
    </form>
@endsection

@push('scripts')
    <script type="module">
        const form = document.getElementById('create-gallery-form');
        const statusEl = document.getElementById('status');
        const submitBtn = document.getElementById('submit-btn');
        const workspaceId = '{{ $workspace->id }}';
        const collectionId = '{{ $collection->id }}';

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitBtn.disabled = true;
            statusEl.innerHTML = '<span class="spinner"></span> Encrypting…';

            try {
                const name = document.getElementById('name').value;
                const description = document.getElementById('description').value;
                const type = document.getElementById('type').value;

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

                const response = await fetch(`/collections/${collectionId}/galleries`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        encrypted_name: encryptedName,
                        name_iv: nameIv,
                        type,
                        encrypted_description: encDesc,
                        description_iv: descIv,
                    }),
                });

                if (response.ok) {
                    const data = await response.json();
                    window.location.href = `/collections/${collectionId}/galleries/${data.gallery_id}`;
                } else {
                    throw new Error('Failed to create gallery');
                }
            } catch (e) {
                statusEl.textContent = 'Error: ' + e.message;
                submitBtn.disabled = false;
            }
        });
    </script>
@endpush
