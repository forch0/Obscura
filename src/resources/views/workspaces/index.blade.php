@extends('layouts.app')

@section('title', 'Workspaces — Obscura')

@section('content')
    <div class="page-header">
        <h2>Workspaces</h2>
        <x-button variant="primary" size="md" pill href="{{ route('workspaces.create') }}">New Workspace</x-button>
    </div>

    <div id="workspaces-list">
        <div class="decrypt-status"><span class="spinner"></span> Decrypting…</div>
    </div>

    @if ($workspaces->isEmpty())
        <x-empty-state title="No workspaces yet" message="Create your first encrypted workspace to get started." :action="route('workspaces.create')" action-label="Create Workspace" />
    @endif
@endsection

@push('scripts')
    @php
        $wsData = $workspaces->map(fn($w) => array_merge(
            $w->only(['id', 'encrypted_name', 'name_iv']),
            ['wrapped_dek' => $w->wrappedDekFor(auth()->user())]
        ));
    @endphp
    <script type="module">
        const workspaces = @json($wsData);

        (async () => {
            const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { setWorkspaceDek, hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = await restorePrivateKey();
            if (!privateKeyHandle) {
                document.getElementById('workspaces-list').innerHTML = '<p class="decrypt-status">Private key not loaded. Please log in again.</p>';
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'gallery-grid';

            for (const ws of workspaces) {
                try {
                    let dekHandle;
                    if (hasWorkspaceDek(ws.id)) {
                        dekHandle = getWorkspaceDek(ws.id);
                    } else {
                        dekHandle = await unsealDek(ws.wrapped_dek, privateKeyHandle);
                        setWorkspaceDek(ws.id, dekHandle);
                    }

                    const name = await decryptName(ws.encrypted_name, dekHandle, ws.name_iv);

                    const card = document.createElement('a');
                    card.href = `/workspaces/${ws.id}`;
                    card.className = 'card';
                    card.style.textDecoration = 'none';
                    card.style.color = 'hsl(var(--foreground))';
                    card.innerHTML = `
                        <h3>${name}</h3>
                        <p class="text-caption text-secondary" style="margin-top:4px">Encrypted workspace</p>
                    `;
                    grid.appendChild(card);
                } catch (e) {
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.innerHTML = `<h3>(decryption failed)</h3><p class="error-text">${e.message}</p>`;
                    grid.appendChild(card);
                }
            }

            document.getElementById('workspaces-list').innerHTML = '';
            document.getElementById('workspaces-list').appendChild(grid);
        })();
    </script>
@endpush
