@extends('layouts.app')

@section('title', 'Workspaces — Obscura')

@section('content')
    <div class="page-header">
        <h2>Your Workspaces</h2>
        <a href="{{ route('workspaces.create') }}" class="btn-primary">New Workspace</a>
    </div>

    <div id="workspace-list">
        <div class="decrypt-status"><span class="spinner"></span> Decrypting workspace names…</div>
    </div>

    @if ($workspaces->isEmpty())
        <div class="empty-state">
            <h3>No workspaces yet</h3>
            <p>Create your first encrypted workspace to get started.</p>
        </div>
    @endif
@endsection

@push('scripts')
    @php
        $wsData = $workspaces->map(fn($w) => $w->only(['id', 'encrypted_name', 'name_iv', 'wrapped_dek_for_owner', 'wrapped_dek_iv']));
    @endphp
    <script type="module">
        const workspaces = @json($wsData);

        if (workspaces.length === 0) {
            document.getElementById('workspace-list').innerHTML = '';
        } else {
            (async () => {
                const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
                const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
                const { setWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

                const privateKeyHandle = getPrivateKeyHandle();
                if (!privateKeyHandle) {
                    document.getElementById('workspace-list').innerHTML =
                        '<p class="decrypt-status">Private key not loaded. Please log in again.</p>';
                    return;
                }

                const grid = document.createElement('div');
                grid.className = 'workspace-grid';

                for (const ws of workspaces) {
                    try {
                        const dekHandle = await unsealDek(ws.wrapped_dek_for_owner, privateKeyHandle);
                        setWorkspaceDek(ws.id, dekHandle);
                        const name = await decryptName(ws.encrypted_name, dekHandle, ws.name_iv);

                        const card = document.createElement('div');
                        card.className = 'workspace-card';
                        card.innerHTML = `
                            <h3>${name}</h3>
                            <p>DEK version ${ws.dek_version ?? 1}</p>
                            <div class="actions">
                                <a href="/workspaces/${ws.id}" class="btn-secondary">Open</a>
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

                document.getElementById('workspace-list').innerHTML = '';
                document.getElementById('workspace-list').appendChild(grid);
            })();
        }
    </script>
@endpush
