@extends('layouts.app')

@section('title', 'Collections — Obscura')

@section('content')
    <div class="page-header">
        <h2 id="workspace-name"><span class="spinner"></span> Loading…</h2>
        <x-button variant="primary" pill href="{{ route('collections.create', $workspace) }}">New Collection</x-button>
    </div>

    <div id="collections-list">
        <div class="decrypt-status"><span class="spinner"></span> Decrypting…</div>
    </div>

    @if ($collections->isEmpty())
        <x-empty-state title="No collections yet" message="Collections organize your galleries within a workspace." :action="route('collections.create', $workspace)" action-label="Create Collection" />
    @endif
@endsection

@push('scripts')
    @php
        $wsData = $workspace->only(['id', 'wrapped_dek_for_owner', 'encrypted_name', 'name_iv']);
        $collectionsData = $collections->map(fn($c) => $c->only(['id', 'encrypted_name', 'name_iv', 'encrypted_description', 'description_iv']));
    @endphp
    <script type="module">
        const workspace = @json($wsData);
        const collections = @json($collectionsData);
        const workspaceId = workspace.id;

        (async () => {
            const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { setWorkspaceDek, hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = getPrivateKeyHandle();
            if (!privateKeyHandle) {
                document.getElementById('collections-list').innerHTML = '<p class="decrypt-status">Private key not loaded.</p>';
                return;
            }

            let dekHandle;
            if (hasWorkspaceDek(workspaceId)) {
                dekHandle = getWorkspaceDek(workspaceId);
            } else {
                dekHandle = await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);
                setWorkspaceDek(workspaceId, dekHandle);
            }

            const wsName = await decryptName(workspace.encrypted_name, dekHandle, workspace.name_iv);
            document.getElementById('workspace-name').textContent = wsName;

            const grid = document.createElement('div');
            grid.className = 'gallery-grid';

            for (const c of collections) {
                try {
                    const name = await decryptName(c.encrypted_name, dekHandle, c.name_iv);
                    const card = document.createElement('a');
                    card.href = `/workspaces/${workspaceId}/collections/${c.id}`;
                    card.className = 'card';
                    card.style.textDecoration = 'none';
                    card.style.color = 'var(--text)';
                    card.innerHTML = `
                        <h3>${name}</h3>
                        <p class="text-caption text-secondary" style="margin-top:4px">Collection</p>
                    `;
                    grid.appendChild(card);
                } catch (e) {
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.innerHTML = `<h3>(decryption failed)</h3><p class="error-text">${e.message}</p>`;
                    grid.appendChild(card);
                }
            }

            document.getElementById('collections-list').innerHTML = '';
            document.getElementById('collections-list').appendChild(grid);
        })();
    </script>
@endpush
