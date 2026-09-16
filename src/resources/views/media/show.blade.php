@extends('layouts.app')

@section('title', 'Media — Obscura')

@section('content')
    <div class="page-header">
        <div>
            <p class="text-caption"><a href="{{ route('galleries.show', [$collection, $gallery]) }}" style="color:hsl(var(--foreground));text-decoration:none">← Back to gallery</a></p>
            <h2 id="media-title" style="margin-top:4px"><span class="spinner"></span> Decrypting…</h2>
        </div>
        <form method="POST" action="{{ route('media.destroy', $media) }}" style="display:inline" onsubmit="return confirm('Delete this media?')">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="danger">Delete</x-button>
        </form>
    </div>

    <div id="media-viewer" style="text-align:center;padding:24px">
        <span class="spinner"></span>
        <p class="decrypt-status" style="margin-top:12px">Decrypting image…</p>
    </div>
    <div id="media-caption"></div>
@endsection

@push('scripts')
    @php
        $mediaData = $media->only(['id', 'encrypted_title', 'title_iv', 'encrypted_caption', 'caption_iv', 'mime_type']);
        $wsData = $workspace->only(['id', 'wrapped_dek_for_owner']);
    @endphp
    <script type="module">
        const media = @json($mediaData);
        const workspace = @json($wsData);
        const mediaId = media.id;
        const workspaceId = workspace.id;

        (async () => {
            const { unsealDek } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');
            const { fetchAndDecryptMedia } = await import('{{ Vite::asset("resources/js/crypto/media-decrypt.js") }}');
            const { decryptTextField } = await import('{{ Vite::asset("resources/js/crypto/media-encrypt.js") }}');

            const privateKeyHandle = await restorePrivateKey();
            if (!privateKeyHandle) {
                document.getElementById('media-title').textContent = 'Private key not loaded';
                return;
            }

            const dekHandle = hasWorkspaceDek(workspaceId)
                ? getWorkspaceDek(workspaceId)
                : await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);

            try {
                if (media.encrypted_title) {
                    document.getElementById('media-title').textContent =
                        await decryptTextField(media.encrypted_title, media.title_iv, dekHandle);
                } else {
                    document.getElementById('media-title').textContent = '(untitled)';
                }
                if (media.encrypted_caption) {
                    const cap = await decryptTextField(media.encrypted_caption, media.caption_iv, dekHandle);
                    document.getElementById('media-caption').innerHTML = `<p class="text-caption text-secondary" style="text-align:center">${cap}</p>`;
                }

                const url = await fetchAndDecryptMedia(`/media/${mediaId}/blob`, dekHandle);
                const viewer = document.getElementById('media-viewer');
                const mime = media.mime_type || '';

                if (mime.startsWith('image/')) {
                    viewer.innerHTML = `<img src="${url}" style="max-width:100%;max-height:80vh;border-radius:8px" alt="">`;
                } else if (mime.startsWith('video/')) {
                    viewer.innerHTML = `<video src="${url}" controls playsinline style="max-width:100%;max-height:80vh;border-radius:8px"></video>`;
                } else if (mime === 'application/pdf') {
                    viewer.innerHTML = `
                        <embed src="${url}" type="application/pdf" style="width:100%;height:75vh;border:1px solid hsl(var(--border));border-radius:8px">
                        <p style="margin-top:12px"><a href="${url}" download class="btn btn-secondary btn-sm">Download PDF</a></p>`;
                } else {
                    viewer.innerHTML = `
                        <div class="card" style="display:inline-block;padding:32px 48px;text-align:center">
                            <p class="font-medium" style="margin-bottom:4px">${mime || 'Unknown type'}</p>
                            <p class="text-caption" style="margin-bottom:16px">Decrypted — ready to download</p>
                            <a href="${url}" download class="btn btn-primary">Download File</a>
                        </div>`;
                }
            } catch (e) {
                document.getElementById('media-viewer').innerHTML = `<p class="decrypt-status">Failed to decrypt: ${e.message}</p>`;
            }
        })();
    </script>
@endpush
