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
        </div>
    </div>

    <div id="gallery-description"></div>

    <div style="margin-top:32px;padding:48px;text-align:center;background:var(--bg-muted);border-radius:12px;color:var(--text-secondary)">
        <p>Gallery grid will appear here (Module 5)</p>
        <p style="font-size:0.8125rem;margin-top:8px">Encrypted media upload + thumbnail rendering</p>
    </div>
@endsection

@push('scripts')
    @php
        $wsData = $workspace->only(['id', 'wrapped_dek_for_owner']);
        $galData = $gallery->only(['id', 'encrypted_name', 'name_iv', 'type', 'encrypted_description', 'description_iv']);
    @endphp
    <script type="module">
        const workspace = @json($wsData);
        const gallery = @json($galData);
        const workspaceId = workspace.id;

        const typeLabels = { private: 'Private', shared: 'Shared', joint: 'Joint' };

        (async () => {
            const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = getPrivateKeyHandle();
            if (!privateKeyHandle) {
                document.getElementById('gallery-name').textContent = 'Private key not loaded';
                return;
            }

            let dekHandle;
            if (hasWorkspaceDek(workspaceId)) {
                dekHandle = getWorkspaceDek(workspaceId);
            } else {
                dekHandle = await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);
            }

            const name = await decryptName(gallery.encrypted_name, dekHandle, gallery.name_iv);
            document.getElementById('gallery-name').textContent = name;
            document.getElementById('gallery-type').textContent = typeLabels[gallery.type] || gallery.type;

            if (gallery.encrypted_description) {
                const desc = await decryptName(gallery.encrypted_description, dekHandle, gallery.description_iv);
                document.getElementById('gallery-description').innerHTML = `<p style="color:var(--text-secondary);font-size:0.875rem">${desc}</p>`;
            }
        })();
    </script>
@endpush
