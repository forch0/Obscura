@extends('layouts.app')

@section('title', 'Edit Gallery — Obscura')

@section('content')
    <div class="page-header">
        <h2>Edit Gallery</h2>
        <x-button variant="secondary" href="{{ route('galleries.show', [$collection, $gallery]) }}">Cancel</x-button>
    </div>

    <div class="card" style="max-width:560px">
        <form id="edit-gallery-form">
            @csrf
            <x-input label="Gallery Name" name="name" required />
            <div class="form-group">
                <label for="type" class="form-label">Type</label>
                <select id="type" class="form-input">
                    <option value="private" @if($gallery->type === 'private') selected @endif>Private — owner only</option>
                    <option value="shared" @if($gallery->type === 'shared') selected @endif>Shared — workspace members can view</option>
                    <option value="joint" @if($gallery->type === 'joint') selected @endif>Joint — editor members can upload/edit</option>
                </select>
            </div>
            <div class="form-group">
                <label for="description" class="form-label">Description (optional)</label>
                <textarea id="description" class="form-input" rows="3"></textarea>
            </div>
            <x-button type="submit" variant="primary" size="lg" pill class="w-full">Save Changes</x-button>
            <p class="decrypt-status" id="status" style="margin-top:12px"></p>
        </form>
    </div>
@endsection

@push('scripts')
    @php
        $wsData = [
            'id' => $workspace->id,
            'wrapped_dek' => $workspace->wrappedDekFor(auth()->user()),
        ];
        $galData = $gallery->only(['id', 'encrypted_name', 'name_iv', 'encrypted_description', 'description_iv']);
    @endphp
    <script type="module">
        const workspace = @json($wsData);
        const gallery = @json($galData);
        const collectionId = '{{ $collection->id }}';
        const form = document.getElementById('edit-gallery-form');
        const statusEl = document.getElementById('status');
        const submitBtn = form.querySelector('button[type=submit]');
        const nameInput = document.getElementById('name');
        const descInput = document.getElementById('description');

        (async () => {
            const { unsealDek, decryptName, encryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = await restorePrivateKey();
            if (!privateKeyHandle) { statusEl.textContent = 'Private key not loaded.'; return; }

            const dekHandle = getWorkspaceDek(workspace.id)
                || await unsealDek(workspace.wrapped_dek, privateKeyHandle);

            try {
                nameInput.value = await decryptName(gallery.encrypted_name, dekHandle, gallery.name_iv);
                if (gallery.encrypted_description) {
                    descInput.value = await decryptName(gallery.encrypted_description, dekHandle, gallery.description_iv);
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

                const res = await fetch(`/collections/${collectionId}/galleries/${gallery.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ encrypted_name: encryptedName, name_iv: nameIv, type: document.getElementById('type').value, encrypted_description: encDesc, description_iv: descIv }),
                });

                if (res.ok) window.location.href = `/collections/${collectionId}/galleries/${gallery.id}`;
                else { statusEl.textContent = 'Failed to save.'; submitBtn.disabled = false; }
            });
        })();
    </script>
@endpush
