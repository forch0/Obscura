@extends('layouts.app')

@section('title', 'Collection — Obscura')

@section('content')
    <div class="page-header">
        <h2 id="collection-name"><span class="spinner"></span> Decrypting…</h2>
        <div>
            <a href="{{ route('galleries.create', $collection) }}" class="btn-primary">New Gallery</a>
            <a href="{{ route('collections.edit', [$workspace, $collection]) }}" class="btn-secondary">Rename</a>
        </div>
    </div>

    <div id="collection-description"></div>

    <h3 style="margin-top:32px;margin-bottom:16px;font-size:1.125rem;font-weight:600">Galleries</h3>
    <div id="galleries-list">
        <div class="decrypt-status"><span class="spinner"></span> Decrypting…</div>
    </div>
@endsection

@push('scripts')
    @php
        $wsData = $workspace->only(['id', 'wrapped_dek_for_owner']);
        $collData = $collection->only(['id', 'encrypted_name', 'name_iv', 'encrypted_description', 'description_iv']);
        $galleriesData = $collection->galleries->map(fn($g) => $g->only(['id', 'encrypted_name', 'name_iv', 'type']));
    @endphp
    <script type="module">
        const workspace = @json($wsData);
        const collection = @json($collData);
        const galleries = @json($galleriesData);
        const workspaceId = workspace.id;
        const collectionId = collection.id;

        (async () => {
            const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = getPrivateKeyHandle();
            if (!privateKeyHandle) {
                document.getElementById('collection-name').textContent = 'Private key not loaded';
                return;
            }

            let dekHandle;
            if (hasWorkspaceDek(workspaceId)) {
                dekHandle = getWorkspaceDek(workspaceId);
            } else {
                dekHandle = await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);
            }

            const name = await decryptName(collection.encrypted_name, dekHandle, collection.name_iv);
            document.getElementById('collection-name').textContent = name;

            if (collection.encrypted_description) {
                const desc = await decryptName(collection.encrypted_description, dekHandle, collection.description_iv);
                document.getElementById('collection-description').innerHTML = `<p style="color:var(--text-secondary);font-size:0.875rem">${desc}</p>`;
            }

            const typeLabels = { private: 'Private', shared: 'Shared', joint: 'Joint' };

            const grid = document.createElement('div');
            grid.className = 'workspace-grid';

            for (const g of galleries) {
                try {
                    const gName = await decryptName(g.encrypted_name, dekHandle, g.name_iv);
                    const card = document.createElement('div');
                    card.className = 'workspace-card';
                    card.innerHTML = `
                        <h3>${gName}</h3>
                        <p>${typeLabels[g.type] || g.type}</p>
                        <div class="actions">
                            <a href="/collections/${collectionId}/galleries/${g.id}" class="btn-secondary">Open</a>
                        </div>
                    `;
                    grid.appendChild(card);
                } catch (e) {
                    const card = document.createElement('div');
                    card.className = 'workspace-card';
                    card.innerHTML = `<h3>(decryption failed)</h3><p>${e.message}</p>`;
                    grid.appendChild(card);
                }
            }

            document.getElementById('galleries-list').innerHTML = '';
            if (galleries.length === 0) {
                document.getElementById('galleries-list').innerHTML = '<div class="empty-state"><h3>No galleries yet</h3><p>Create your first gallery in this collection.</p></div>';
            } else {
                document.getElementById('galleries-list').appendChild(grid);
            }
        })();
    </script>
@endpush
