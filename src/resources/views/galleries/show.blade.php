@extends('layouts.app')

@section('title', 'Gallery — Obscura')

@section('content')
    <div class="page-header">
        <div>
            <p class="text-caption"><a href="{{ route('collections.show', [$workspace, $collection]) }}" style="color:hsl(var(--foreground));text-decoration:none">← Back to collection</a></p>
            <h2 id="gallery-name" style="margin-top:4px"><span class="spinner"></span> Decrypting…</h2>
            <p id="gallery-type" class="text-caption"></p>
        </div>
        <div class="flex gap-2">
            @if($gallery->type !== 'private')
                <x-button variant="secondary" href="{{ route('galleries.members', [$collection, $gallery]) }}">Members</x-button>
            @endif
            <x-button variant="secondary" href="{{ route('galleries.edit', [$collection, $gallery]) }}">Edit</x-button>
            <x-button variant="primary" id="upload-btn">Upload</x-button>
            <input type="file" id="upload-input" accept="image/*" style="display:none">
        </div>
    </div>

    <div id="gallery-description"></div>
    <div id="upload-status" class="decrypt-status" style="display:none"></div>

    <h3 style="margin-top:32px;margin-bottom:16px">Media</h3>
    <div id="media-grid" class="gallery-grid"></div>
    <div id="media-empty" style="display:none">
        <x-empty-state title="No media yet" message="Upload encrypted images to this gallery." />
    </div>
@endsection

@push('scripts')
    @php
        $wsData = $workspace->only(['id', 'wrapped_dek_for_owner']);
        $galData = $gallery->only(['id', 'encrypted_name', 'name_iv', 'type', 'encrypted_description', 'description_iv']);
        $collData = $collection->only(['id']);
    @endphp
    <script type="module">
        const workspace = @json($wsData);
        const gallery = @json($galData);
        const collection = @json($collData);
        const workspaceId = workspace.id;
        const galleryId = gallery.id;
        const collectionId = collection.id;
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const typeLabels = { private: 'Private', shared: 'Shared', joint: 'Joint' };
        const typeBadge = { private: '', shared: 'badge-primary', joint: 'badge-secondary' };

        let dekHandle;

        (async () => {
            const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');
            const { fetchAndDecryptMedia } = await import('{{ Vite::asset("resources/js/crypto/media-decrypt.js") }}');
            const { decryptTextField, encryptFile } = await import('{{ Vite::asset("resources/js/crypto/media-encrypt.js") }}');

            const privateKeyHandle = await restorePrivateKey();
            if (!privateKeyHandle) {
                document.getElementById('gallery-name').textContent = 'Private key not loaded';
                return;
            }

            dekHandle = hasWorkspaceDek(workspaceId)
                ? getWorkspaceDek(workspaceId)
                : await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);

            const name = await decryptName(gallery.encrypted_name, dekHandle, gallery.name_iv);
            document.getElementById('gallery-name').textContent = name;
            document.getElementById('gallery-type').innerHTML = `<span class="badge ${typeBadge[gallery.type] || ''}">${typeLabels[gallery.type] || gallery.type}</span>`;

            if (gallery.encrypted_description) {
                const desc = await decryptName(gallery.encrypted_description, dekHandle, gallery.description_iv);
                document.getElementById('gallery-description').innerHTML = `<p class="text-caption text-secondary">${desc}</p>`;
            }

            // Load media
            const res = await fetch(`/galleries/${galleryId}/media`, { headers: { 'Accept': 'application/json' } });
            const { media } = await res.json();

            const grid = document.getElementById('media-grid');
            if (!media || media.length === 0) {
                document.getElementById('media-empty').style.display = '';
            }

            for (const m of media) {
                const card = document.createElement('div');
                card.className = 'gallery-card';
                card.style.cursor = 'pointer';

                let title = '(untitled)';
                try {
                    if (m.encrypted_title) title = await decryptTextField(m.encrypted_title, m.title_iv, dekHandle);
                } catch (e) {}

                card.innerHTML = `
                    <div class="thumb" id="thumb-${m.id}"><span class="spinner"></span></div>
                    <div class="meta">
                        <p class="title">${title}</p>
                        <p class="detail">${(m.size / 1024).toFixed(0)} KB</p>
                    </div>
                `;
                card.addEventListener('click', () => { window.location.href = `/media/${m.id}`; });
                grid.appendChild(card);

                if (m.has_thumbnail) {
                    fetchAndDecryptMedia(`/media/${m.id}/thumbnail`, dekHandle)
                        .then(url => {
                            const el = document.getElementById(`thumb-${m.id}`);
                            el.innerHTML = `<img src="${url}" alt="" loading="lazy">`;
                        })
                        .catch(() => {
                            document.getElementById(`thumb-${m.id}`).innerHTML = '<span class="text-muted">No preview</span>';
                        });
                } else {
                    document.getElementById(`thumb-${m.id}`).innerHTML = '<span class="text-muted">No preview</span>';
                }
            }

            // Upload
            const uploadBtn = document.getElementById('upload-btn');
            const uploadInput = document.getElementById('upload-input');
            const uploadStatus = document.getElementById('upload-status');

            uploadBtn.addEventListener('click', () => uploadInput.click());
            uploadInput.addEventListener('change', async () => {
                const file = uploadInput.files[0];
                if (!file) return;

                uploadStatus.style.display = '';
                uploadStatus.innerHTML = '<span class="spinner"></span> Encrypting…';
                uploadBtn.disabled = true;

                try {
                    const payload = await encryptFile(file, dekHandle);

                    uploadStatus.innerHTML = '<span class="spinner"></span> Uploading…';
                    const formData = new FormData();
                    formData.append('ciphertext', payload.ciphertext);
                    if (payload.thumbnail) formData.append('thumbnail', payload.thumbnail);
                    formData.append('cek_wrapped', payload.cek_wrapped);
                    formData.append('iv', payload.iv);
                    if (payload.thumb_iv) formData.append('thumb_iv', payload.thumb_iv);
                    formData.append('mime_type', payload.mime_type);
                    formData.append('size', payload.size);
                    formData.append('encrypted_title', payload.encrypted_title);
                    formData.append('title_iv', payload.title_iv);

                    const res = await fetch(`/galleries/${galleryId}/media`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: formData,
                    });

                    if (!res.ok) throw new Error('Upload failed');
                    location.reload();
                } catch (e) {
                    uploadStatus.textContent = 'Error: ' + e.message;
                    uploadBtn.disabled = false;
                }
            });
        })();
    </script>
@endpush
