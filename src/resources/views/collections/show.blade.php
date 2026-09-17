@extends('layouts.app')

@section('title', 'Collection — Obscura')

@section('content')
    <p class="text-caption" style="margin-bottom:8px"><a href="{{ route('collections.index', $workspace) }}" style="color:hsl(var(--foreground));text-decoration:none">← Back to collections</a></p>
    <div class="page-header keep-row">
        <h2 id="collection-name"><span class="spinner"></span> Decrypting…</h2>
        <div class="flex gap-2">
            <x-button variant="primary" size="md" pill href="{{ route('galleries.create', $collection) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" width="14" height="14" style="vertical-align:-2px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Gallery</x-button>
            <x-button variant="secondary" class="btn-responsive" href="{{ route('collections.edit', [$workspace, $collection]) }}">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                <span class="btn-label">Rename</span>
            </x-button>
        </div>
    </div>

    <div id="collection-description"></div>

    <h3 style="margin-top:32px;margin-bottom:16px">Galleries</h3>
    <div id="galleries-list">
        <div class="decrypt-status"><span class="spinner"></span> Decrypting…</div>
    </div>
@endsection

@push('scripts')
    @php
        $wsData = [
            'id' => $workspace->id,
            'wrapped_dek' => $workspace->wrappedDekFor(auth()->user()),
        ];
        $collData = $collection->only(['id', 'encrypted_name', 'name_iv', 'encrypted_description', 'description_iv']);
        $galleriesData = $collection->galleries->map(fn($g) => $g->only(['id', 'encrypted_name', 'name_iv', 'type']));
    @endphp
    <script type="module">
        const workspace = @json($wsData);
        const collection = @json($collData);
        const galleries = @json($galleriesData);
        const workspaceId = workspace.id;
        const collectionId = collection.id;

        const typeLabels = { private: 'Private', shared: 'Shared', joint: 'Joint' };
        const typeBadge = { private: '', shared: 'badge-primary', joint: 'badge-secondary' };

        (async () => {
            const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = await restorePrivateKey();
            if (!privateKeyHandle) {
                document.getElementById('collection-name').textContent = 'Private key not loaded';
                return;
            }

            let dekHandle;
            if (hasWorkspaceDek(workspaceId)) {
                dekHandle = getWorkspaceDek(workspaceId);
            } else {
                dekHandle = await unsealDek(workspace.wrapped_dek, privateKeyHandle);
            }

            const name = await decryptName(collection.encrypted_name, dekHandle, collection.name_iv);
            document.getElementById('collection-name').textContent = name;

            if (collection.encrypted_description) {
                const desc = await decryptName(collection.encrypted_description, dekHandle, collection.description_iv);
                document.getElementById('collection-description').innerHTML = `<p class="text-caption text-secondary">${desc}</p>`;
            }

            const grid = document.createElement('div');
            grid.className = 'gallery-grid';

            for (const g of galleries) {
                try {
                    const gName = await decryptName(g.encrypted_name, dekHandle, g.name_iv);
                    const card = document.createElement('a');
                    card.href = `/collections/${collectionId}/galleries/${g.id}`;
                    card.className = 'card';
                    card.style.textDecoration = 'none';
                    card.style.color = 'hsl(var(--foreground))';
                    card.innerHTML = `
                        <h3>${gName}</h3>
                        <p style="margin-top:4px"><span class="badge ${typeBadge[g.type] || ''}">${typeLabels[g.type] || g.type}</span></p>
                    `;
                    grid.appendChild(card);
                } catch (e) {
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.innerHTML = `<h3>(decryption failed)</h3><p class="error-text">${e.message}</p>`;
                    grid.appendChild(card);
                }
            }

            document.getElementById('galleries-list').innerHTML = '';
            if (galleries.length === 0) {
                document.getElementById('galleries-list').innerHTML = '<div class="empty-state"><h3>No galleries yet</h3><p class="text-secondary">Create your first gallery in this collection.</p></div>';
            } else {
                document.getElementById('galleries-list').appendChild(grid);
            }
        })();
    </script>
@endpush
