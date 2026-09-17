@extends('layouts.app')

@section('title', 'Gallery — Obscura')

@section('content')
    <p class="text-caption" style="margin-bottom:8px"><a href="{{ route('collections.show', [$workspace, $collection]) }}" style="color:hsl(var(--foreground));text-decoration:none">← Back to collection</a></p>
    <div class="page-header keep-row">
        <div>
            <h2 id="gallery-name"><span class="spinner"></span> Decrypting…</h2>
            <p id="gallery-type" class="text-caption"></p>
        </div>
        <div class="flex gap-2" style="flex-wrap:wrap">
            @if($gallery->type !== 'private')
                <x-button variant="secondary" class="btn-responsive" href="{{ route('galleries.members', [$collection, $gallery]) }}">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span class="btn-label">Members</span>
                </x-button>
            @endif
            <x-dropdown label="Manage" variant="secondary" size="sm" align="right">
                <x-dropdown.item href="{{ route('galleries.edit', [$collection, $gallery]) }}">Edit</x-dropdown.item>
                <x-dropdown.separator />
                <x-dropdown.item danger id="delete-gallery-btn">Delete gallery</x-dropdown.item>
            </x-dropdown>
            <form id="delete-gallery-form" method="POST" action="{{ route('galleries.destroy', [$collection, $gallery]) }}" style="display:none">
                @csrf
                @method('DELETE')
            </form>
            <x-button variant="primary" id="upload-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="vertical-align:-2px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Upload</x-button>
            <input type="file" id="upload-input" accept="image/*,video/*,.heic,.heif,application/pdf" multiple style="display:none">
        </div>
    </div>

    <div id="gallery-description"></div>
    <div id="upload-status" class="decrypt-status" style="display:none"></div>
    <p class="text-caption text-muted" id="upload-limits" style="margin-top:4px"></p>

    <h3 style="margin-top:32px;margin-bottom:16px">Media</h3>
    <div id="media-grid" class="gallery-grid"></div>
    <div id="media-empty" style="display:none">
        <x-empty-state title="No media yet" message="Upload encrypted images to this gallery." />
    </div>

    {{-- Lightbox --}}
    <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Media viewer">
        <div class="lightbox-toolbar">
            <span class="lightbox-counter" id="lb-counter"></span>
            <span class="lightbox-title" id="lb-title"></span>
            <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
                <button class="lightbox-close" id="lb-delete" aria-label="Delete" title="Delete media">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
                <button class="lightbox-close" id="lb-close" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="lightbox-body">
            <button class="lightbox-nav lightbox-prev" id="lb-prev" aria-label="Previous">&#8249;</button>
            <div class="lightbox-media" id="lb-media"></div>
            <button class="lightbox-nav lightbox-next" id="lb-next" aria-label="Next">&#8250;</button>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $wsData = [
            'id' => $workspace->id,
            'wrapped_dek' => $workspace->wrappedDekFor(auth()->user()),
        ];
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

        // Upload limits
        const MAX_FILES_PER_BATCH = 20;
        const MAX_FILE_MB = 100;
        const MAX_TOTAL_MB = 500;
        const MAX_FILE_BYTES = MAX_FILE_MB * 1024 * 1024;
        const MAX_TOTAL_BYTES = MAX_TOTAL_MB * 1024 * 1024;

        const fileIcon = (mime) => {
            if (mime?.startsWith('video/')) return '&#9654;';
            if (mime === 'application/pdf') return '&#128441;';
            if (mime === 'image/heic' || mime === 'image/heif') return '&#128444;';
            return '&#128206;';
        };
        const fileKind = (mime) => {
            if (mime?.startsWith('video/')) return 'Video';
            if (mime === 'application/pdf') return 'PDF';
            if (mime === 'image/heic' || mime === 'image/heif') return 'HEIC';
            return 'File';
        };
        const fmtBytes = (b) => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';

        let dekHandle;
        let mediaList = [];
        const mediaTitles = {};

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
                : await unsealDek(workspace.wrapped_dek, privateKeyHandle);

            const name = await decryptName(gallery.encrypted_name, dekHandle, gallery.name_iv);
            document.getElementById('gallery-name').textContent = name;
            document.getElementById('gallery-type').innerHTML = `<span class="badge ${typeBadge[gallery.type] || ''}">${typeLabels[gallery.type] || gallery.type}</span>`;

            // GitHub-style delete: type the gallery name to confirm
            document.getElementById('delete-gallery-btn').addEventListener('click', async () => {
                const ok = await ObscuraDialog.confirmDelete({
                    entityType: 'gallery',
                    name,
                    message: `This will permanently delete <strong>${name}</strong> and all media inside it. This cannot be undone.`,
                });
                if (ok) document.getElementById('delete-gallery-form').submit();
            });
            document.getElementById('upload-limits').textContent = `Up to ${MAX_FILES_PER_BATCH} files per batch · ${MAX_FILE_MB} MB each · ${MAX_TOTAL_MB} MB total`;

            if (gallery.encrypted_description) {
                const desc = await decryptName(gallery.encrypted_description, dekHandle, gallery.description_iv);
                document.getElementById('gallery-description').innerHTML = `<p class="text-caption text-secondary">${desc}</p>`;
            }

            // Load media
            const res = await fetch(`/galleries/${galleryId}/media`, { headers: { 'Accept': 'application/json' } });
            const { media } = await res.json();
            mediaList = media || [];

            const grid = document.getElementById('media-grid');
            if (mediaList.length === 0) {
                document.getElementById('media-empty').style.display = '';
            }

            for (let i = 0; i < mediaList.length; i++) {
                const m = mediaList[i];
                const card = document.createElement('div');
                card.className = 'gallery-card';
                card.style.cursor = 'pointer';

                let title = '(untitled)';
                try {
                    if (m.encrypted_title) title = await decryptTextField(m.encrypted_title, m.title_iv, dekHandle);
                } catch (e) {}
                mediaTitles[m.id] = title;

                card.innerHTML = `
                    <div class="thumb" id="thumb-${m.id}"><span class="spinner"></span></div>
                    <div class="meta">
                        <p class="title">${title}</p>
                        <p class="detail">${fmtBytes(m.size)}</p>
                    </div>
                `;
                card.addEventListener('click', () => openLightbox(mediaList.findIndex(x => x.id === m.id)));
                grid.appendChild(card);

                if (m.has_thumbnail) {
                    fetchAndDecryptMedia(`/media/${m.id}/thumbnail`, dekHandle)
                        .then(url => {
                            const el = document.getElementById(`thumb-${m.id}`);
                            el.innerHTML = `<img src="${url}" alt="" loading="lazy">`;
                        })
                        .catch(() => {
                            document.getElementById(`thumb-${m.id}`).innerHTML = `<span style="font-size:1.5rem;color:hsl(var(--muted-foreground))">${fileIcon(m.mime_type)}</span>`;
                        });
                } else {
                    document.getElementById(`thumb-${m.id}`).innerHTML = `<div style="text-align:center"><span style="font-size:1.5rem;color:hsl(var(--muted-foreground))">${fileIcon(m.mime_type)}</span><p class="text-caption" style="margin-top:4px">${fileKind(m.mime_type)}</p></div>`;
                }
            }

            // ---- Upload (multi-file with limits) ----
            const uploadBtn = document.getElementById('upload-btn');
            const uploadInput = document.getElementById('upload-input');
            const uploadStatus = document.getElementById('upload-status');

            uploadBtn.addEventListener('click', () => uploadInput.click());
            uploadInput.addEventListener('change', async () => {
                const files = [...uploadInput.files];
                uploadInput.value = '';
                if (!files.length) return;

                // Enforce batch limits
                if (files.length > MAX_FILES_PER_BATCH) {
                    uploadStatus.style.display = '';
                    uploadStatus.textContent = `Too many files — max ${MAX_FILES_PER_BATCH} per batch.`;
                    return;
                }
                const totalSize = files.reduce((n, f) => n + f.size, 0);
                const oversize = files.find(f => f.size > MAX_FILE_BYTES);
                if (oversize) {
                    uploadStatus.style.display = '';
                    uploadStatus.textContent = `"${oversize.name}" exceeds the ${MAX_FILE_MB} MB per-file limit.`;
                    return;
                }
                if (totalSize > MAX_TOTAL_BYTES) {
                    uploadStatus.style.display = '';
                    uploadStatus.textContent = `Batch is ${fmtBytes(totalSize)} — max ${MAX_TOTAL_MB} MB total.`;
                    return;
                }

                uploadStatus.style.display = '';
                uploadBtn.disabled = true;
                let done = 0, failed = 0;

                for (const file of files) {
                    try {
                        uploadStatus.innerHTML = `<span class="spinner"></span> Encrypting ${done + 1} of ${files.length} — ${file.name}`;
                        const payload = await encryptFile(file, dekHandle);

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
                        done++;
                    } catch (e) {
                        failed++;
                        console.error(`Upload failed: ${file.name}`, e);
                    }
                }

                if (failed === 0) {
                    location.reload();
                } else {
                    uploadStatus.textContent = `${done} uploaded, ${failed} failed.`;
                    uploadBtn.disabled = false;
                }
            });
        })();

        // ---- Lightbox ----
        const lightbox = document.getElementById('lightbox');
        const lbMedia = document.getElementById('lb-media');
        const lbTitle = document.getElementById('lb-title');
        const lbCounter = document.getElementById('lb-counter');
        let lbIndex = 0;
        let lbBlobUrl = null;

        async function openLightbox(index) {
            lbIndex = index;
            lightbox.classList.add('open');
            document.body.style.overflow = 'hidden';
            await showMedia(lbIndex);
        }

        function closeLightbox() {
            lightbox.classList.remove('open');
            document.body.style.overflow = '';
            if (lbBlobUrl) { URL.revokeObjectURL(lbBlobUrl); lbBlobUrl = null; }
            lbMedia.innerHTML = '';
        }

        async function showMedia(index) {
            if (index < 0 || index >= mediaList.length) return;
            lbIndex = index;
            const m = mediaList[index];

            lbCounter.textContent = `${index + 1} / ${mediaList.length}`;
            lbTitle.textContent = mediaTitles[m.id] || '';
            document.getElementById('lb-prev').disabled = index === 0;
            document.getElementById('lb-next').disabled = index === mediaList.length - 1;

            if (lbBlobUrl) { URL.revokeObjectURL(lbBlobUrl); lbBlobUrl = null; }
            lbMedia.innerHTML = '<span class="spinner" style="border-color:hsl(0 0% 100% / 0.3);border-top-color:#fff"></span>';

            try {
                const { fetchAndDecryptMedia } = await import('{{ Vite::asset("resources/js/crypto/media-decrypt.js") }}');
                const url = await fetchAndDecryptMedia(`/media/${m.id}/blob`, dekHandle);
                lbBlobUrl = url;

                if (m.mime_type?.startsWith('video/')) {
                    lbMedia.className = 'lightbox-media';
                    lbMedia.innerHTML = `<video src="${url}" controls autoplay playsinline></video>`;
                } else if (m.mime_type === 'application/pdf') {
                    lbMedia.className = 'lightbox-media lb-pdf';
                    lbMedia.innerHTML = `<iframe src="${url}" title="PDF"></iframe>`;
                } else if (m.mime_type?.startsWith('image/')) {
                    lbMedia.className = 'lightbox-media';
                    lbMedia.innerHTML = `<img src="${url}" alt="">`;
                } else {
                    lbMedia.className = 'lightbox-media';
                    // Unknown/unsupported — offer download
                    lbMedia.innerHTML = `
                        <div class="file-fallback">
                            <div class="file-glyph">${fileIcon(m.mime_type)}</div>
                            <p style="margin:12px 0">${mediaTitles[m.id] || 'File'}</p>
                            <a href="${url}" download="${mediaTitles[m.id] || 'file'}" class="btn btn-secondary" style="text-decoration:none">Download</a>
                        </div>`;
                }
            } catch (e) {
                lbMedia.innerHTML = `<p style="color:hsl(0 0% 100% / 0.7)">Unable to decrypt media.</p>`;
            }
        }

        document.getElementById('lb-close').addEventListener('click', closeLightbox);
        document.getElementById('lb-prev').addEventListener('click', () => showMedia(lbIndex - 1));
        document.getElementById('lb-next').addEventListener('click', () => showMedia(lbIndex + 1));
        document.getElementById('lb-delete').addEventListener('click', async () => {
            const m = mediaList[lbIndex];
            if (!m) return;

            const title = mediaTitles[m.id] || 'this media';
            const ok = await ObscuraDialog.confirmDelete({
                entityType: 'media',
                name: title,
                message: `This will permanently delete <strong>${title}</strong>. This cannot be undone.`,
            });
            if (!ok) return;

            const res = await fetch(`/media/${m.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (!res.ok) { ObscuraDialog.toast('Delete failed', 'error'); return; }

            mediaList.splice(lbIndex, 1);
            if (mediaList.length === 0) { closeLightbox(); location.reload(); return; }
            await showMedia(Math.min(lbIndex, mediaList.length - 1));
            // Rebuild grid card list
            document.getElementById(`thumb-${m.id}`)?.closest('.gallery-card')?.remove();
            document.getElementById('lb-counter').textContent = `${lbIndex + 1} / ${mediaList.length}`;
        });
        lightbox.addEventListener('click', (e) => { if (e.target === lightbox || e.target.classList.contains('lightbox-body')) closeLightbox(); });
        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('open')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') showMedia(lbIndex - 1);
            if (e.key === 'ArrowRight') showMedia(lbIndex + 1);
        });
    </script>
@endpush
