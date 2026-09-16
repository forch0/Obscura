@extends('layouts.app')

@section('title', 'Gallery — Obscura')

@section('content')
    <div class="page-header">
        <div>
            <p style="font-size:0.875rem;color:var(--text-secondary)"><a href="{{ route('collections.show', [$workspace, $collection]) }}" style="color:var(--accent);text-decoration:none">← Back to collection</a></p>
            <h2 id="gallery-name" style="margin-top:4px"><span class="spinner"></span> Decrypting…</h2>
            <p id="gallery-type" style="font-size:0.8125rem;color:var(--text-secondary)"></p>
        </div>
        <div>
            @if($gallery->type !== 'private')
                <a href="{{ route('galleries.members', [$collection, $gallery]) }}" class="btn-secondary">Members</a>
            @endif
            <a href="{{ route('galleries.edit', [$collection, $gallery]) }}" class="btn-secondary">Edit</a>
            <button type="button" class="btn-primary" id="upload-btn">Upload</button>
            <input type="file" id="upload-input" accept="image/*" style="display:none">
        </div>
    </div>

    <div id="gallery-description"></div>

    <div id="upload-status" class="decrypt-status" style="display:none"></div>

    <h3 style="margin-top:32px;margin-bottom:16px;font-size:1.125rem;font-weight:600">Media</h3>
    <div id="media-grid" class="workspace-grid"></div>
    <div id="media-empty" class="empty-state" style="display:none">
        <h3>No media yet</h3>
        <p>Upload encrypted images to this gallery.</p>
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

        let dekHandle;

        (async () => {
            const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');
            const { fetchAndDecryptMedia } = await import('{{ Vite::asset("resources/js/crypto/media-decrypt.js") }}');
            const { decryptTextField, encryptFile } = await import('{{ Vite::asset("resources/js/crypto/media-encrypt.js") }}');

            const privateKeyHandle = getPrivateKeyHandle();
            if (!privateKeyHandle) {
                document.getElementById('gallery-name').textContent = 'Private key not loaded';
                return;
            }

            dekHandle = hasWorkspaceDek(workspaceId)
                ? getWorkspaceDek(workspaceId)
                : await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);

            const name = await decryptName(gallery.encrypted_name, dekHandle, gallery.name_iv);
            document.getElementById('gallery-name').textContent = name;
            document.getElementById('gallery-type').textContent = typeLabels[gallery.type] || gallery.type;

            if (gallery.encrypted_description) {
                const desc = await decryptName(gallery.encrypted_description, dekHandle, gallery.description_iv);
                document.getElementById('gallery-description').innerHTML = `<p style="color:var(--text-secondary);font-size:0.875rem">${desc}</p>`;
            }

            // Load media list
            const res = await fetch(`/galleries/${galleryId}/media`, { headers: { 'Accept': 'application/json' } });
            const { media } = await res.json();

            const grid = document.getElementById('media-grid');
            if (!media || media.length === 0) {
                document.getElementById('media-empty').style.display = '';
            }

            for (const m of media) {
                const card = document.createElement('div');
                card.className = 'workspace-card';
                card.style.padding = '0';
                card.style.overflow = 'hidden';

                let title = '(untitled)';
                try {
                    if (m.encrypted_title) title = await decryptTextField(m.encrypted_title, m.title_iv, dekHandle);
                } catch (e) {}

                card.innerHTML = `
                    <div style="aspect-ratio:1;background:var(--bg-muted);display:flex;align-items:center;justify-content:center" id="thumb-${m.id}">
                        <span class="spinner"></span>
                    </div>
                    <div style="padding:12px">
                        <p style="font-size:0.875rem;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${title}</p>
                        <p style="font-size:0.75rem;color:var(--text-secondary)">${(m.size / 1024).toFixed(0)} KB</p>
                    </div>
                `;
                card.querySelector('div').addEventListener('click', () => {
                    window.location.href = `/media/${m.id}`;
                });
                grid.appendChild(card);

                // Decrypt thumbnail
                if (m.has_thumbnail) {
                    fetchAndDecryptMedia(`/media/${m.id}/thumbnail`, dekHandle)
                        .then(url => {
                            const el = document.getElementById(`thumb-${m.id}`);
                            el.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:cover" alt="">`;
                        })
                        .catch(() => {
                            document.getElementById(`thumb-${m.id}`).innerHTML = '<span style="color:var(--text-secondary)">No preview</span>';
                        });
                } else {
                    document.getElementById(`thumb-${m.id}`).innerHTML = '<span style="color:var(--text-secondary)">No preview</span>';
                }
            }

            // Upload handler
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
