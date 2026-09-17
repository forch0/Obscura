@extends('layouts.guest')

@section('title', 'Shared Gallery — Obscura')

@section('content')
    <div class="page-header">
        <div>
            <h2 id="workspace-name"><span class="spinner"></span> Unlocking…</h2>
            <p class="text-caption" id="access-meta"></p>
        </div>
    </div>

    <div id="gallery-list">
        <div class="decrypt-status"><span class="spinner"></span> Decrypting…</div>
    </div>

    {{-- Lightbox --}}
    <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Media viewer">
        <div class="lightbox-toolbar">
            <span class="lightbox-counter" id="lb-counter"></span>
            <span class="lightbox-title" id="lb-title"></span>
            <button class="lightbox-close" id="lb-close" aria-label="Close">&times;</button>
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
        $accessData = [
            'workspace_name' => $workspace->encrypted_name,
            'workspace_name_iv' => $workspace->name_iv,
            'wrapped_dek' => $code->wrapped_dek,
            'raw_code' => $session['raw_code'],
            'code_salt' => $session['code_salt'],
            'scope' => $session['scope'],
            'permissions' => $session['permissions'],
            'expires_at' => $session['expires_at'],
        ];
    @endphp
    <script type="module">
        const access = @json($accessData);
        const galleries = @json($galleries);
        const typeLabels = { private: 'Private', shared: 'Shared', joint: 'Joint' };
        const fileIcon = (mime) => {
            if (mime?.startsWith('video/')) return '&#9654;';
            if (mime === 'application/pdf') return '&#128441;';
            return '&#128206;';
        };
        const fmtBytes = (b) => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';

        let dekHandle = null;
        let mediaList = [];
        const mediaTitles = {};

        (async () => {
            const { deriveCodeKey, unsealDekWithCode } = await import('{{ Vite::asset("resources/js/crypto/code-key.js") }}');
            const { decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');

            // Unseal the workspace DEK with the access code
            const codeKey = await deriveCodeKey(access.raw_code, access.code_salt);
            dekHandle = await unsealDekWithCode(access.wrapped_dek, codeKey);

            // Workspace name + expiry
            const wsName = await decryptName(access.workspace_name, dekHandle, access.workspace_name_iv);
            document.getElementById('workspace-name').textContent = wsName;
            document.getElementById('access-meta').textContent =
                `${typeLabels[access.scope] || access.scope} access · Expires ${new Date(access.expires_at).toLocaleString()}`;

            // Render galleries
            const list = document.getElementById('gallery-list');
            list.innerHTML = '';

            if (galleries.length === 0) {
                list.innerHTML = '<div class="empty-state"><h3>No galleries</h3><p class="text-secondary">This code does not unlock any galleries.</p></div>';
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'gallery-grid';
            list.appendChild(grid);

            for (const g of galleries) {
                let gName = '(unnamed)';
                try { gName = await decryptName(g.encrypted_name, dekHandle, g.name_iv); } catch (e) {}

                const card = document.createElement('div');
                card.className = 'card';
                card.style.cursor = 'pointer';
                card.innerHTML = `
                    <h3>${gName}</h3>
                    <p class="text-caption text-secondary" id="gal-status-${g.id}"><span class="spinner"></span></p>
                `;
                card.addEventListener('click', () => openGallery(g.id, gName, card));
                grid.appendChild(card);
            }
        })();

        async function openGallery(galleryId, galleryName, card) {
            const statusEl = document.getElementById(`gal-status-${galleryId}`);
            statusEl.innerHTML = '<span class="spinner"></span> Loading…';

            const res = await fetch(`/access/galleries/${galleryId}/media`, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) { statusEl.textContent = 'Could not load gallery.'; return; }

            const { media } = await res.json();
            mediaList = media || [];

            if (mediaList.length === 0) {
                statusEl.textContent = 'Empty gallery';
                return;
            }

            // Replace card area with media grid
            const listEl = document.getElementById('gallery-list');
            listEl.innerHTML = `
                <p class="text-caption" style="margin-bottom:12px"><a href="#" id="back-to-galleries" style="color:hsl(var(--foreground));text-decoration:none">← Back to galleries</a></p>
                <h3 style="margin-bottom:16px">${galleryName}</h3>
                <div class="gallery-grid" id="media-grid"></div>
            `;
            document.getElementById('back-to-galleries').addEventListener('click', (e) => {
                e.preventDefault();
                location.reload();
            });

            const grid = document.getElementById('media-grid');
            const { decryptTextField } = await import('{{ Vite::asset("resources/js/crypto/media-encrypt.js") }}');
            const { fetchAndDecryptMedia } = await import('{{ Vite::asset("resources/js/crypto/media-decrypt.js") }}');

            for (let i = 0; i < mediaList.length; i++) {
                const m = mediaList[i];
                const cardEl = document.createElement('div');
                cardEl.className = 'gallery-card';
                cardEl.style.cursor = 'pointer';

                let title = '(untitled)';
                try { title = await decryptTextField(m.encrypted_title, m.title_iv, dekHandle); } catch (e) {}
                mediaTitles[m.id] = title;

                cardEl.innerHTML = `
                    <div class="thumb" id="athumb-${m.id}"><span class="spinner"></span></div>
                    <div class="meta">
                        <p class="title">${title}</p>
                        <p class="detail">${fmtBytes(m.size)}</p>
                    </div>
                `;
                cardEl.addEventListener('click', () => openLightbox(i));
                grid.appendChild(cardEl);

                if (m.has_thumbnail) {
                    fetchAndDecryptMedia(`/access/media/${m.id}/thumbnail`, dekHandle)
                        .then(url => {
                            document.getElementById(`athumb-${m.id}`).innerHTML = `<img src="${url}" alt="" loading="lazy">`;
                        })
                        .catch(() => {
                            document.getElementById(`athumb-${m.id}`).innerHTML = `<span style="font-size:1.5rem;color:hsl(var(--muted-foreground))">${fileIcon(m.mime_type)}</span>`;
                        });
                } else {
                    document.getElementById(`athumb-${m.id}`).innerHTML = `<div style="text-align:center"><span style="font-size:1.5rem;color:hsl(var(--muted-foreground))">${fileIcon(m.mime_type)}</span><p class="text-caption" style="margin-top:4px">${m.mime_type === 'application/pdf' ? 'PDF' : 'File'}</p></div>`;
                }
            }
        }

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
                const url = await fetchAndDecryptMedia(`/access/media/${m.id}/blob`, dekHandle);
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
        lightbox.addEventListener('click', (e) => { if (e.target === lightbox || e.target.classList.contains('lightbox-body')) closeLightbox(); });
        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('open')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') showMedia(lbIndex - 1);
            if (e.key === 'ArrowRight') showMedia(lbIndex + 1);
        });
    </script>
@endpush
