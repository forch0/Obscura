@extends('layouts.app')

@section('title', 'Workspace — Obscura')

@section('content')
    <div class="page-header">
        <h2 id="workspace-name"><span class="spinner"></span> Decrypting…</h2>
        <div>
            <a href="{{ route('workspaces.edit', $workspace) }}" class="btn-secondary">Rename</a>
            <form method="POST" action="{{ route('workspaces.destroy', $workspace) }}" style="display:inline" onsubmit="return confirm('Delete this workspace? All collections and galleries will be permanently lost.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger">Delete</button>
            </form>
        </div>
    </div>

    <div id="workspace-content">
        <p class="decrypt-status">DEK version: {{ $workspace->dek_version }}</p>
        @if ($workspace->rekeyed_at)
            <p class="decrypt-status">Last re-keyed: {{ $workspace->rekeyed_at->format('M j, Y g:i A') }}</p>
        @endif
    </div>
@endsection

@push('scripts')
    @php
        $wsData = $workspace->only(['id', 'encrypted_name', 'name_iv', 'wrapped_dek_for_owner', 'wrapped_dek_iv']);
    @endphp
    <script type="module">
        const workspace = @json($wsData);

        (async () => {
            const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { setWorkspaceDek, hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = getPrivateKeyHandle();
            if (!privateKeyHandle) {
                document.getElementById('workspace-name').textContent = 'Private key not loaded';
                return;
            }

            let dekHandle;
            if (hasWorkspaceDek(workspace.id)) {
                dekHandle = getWorkspaceDek(workspace.id);
            } else {
                dekHandle = await unsealDek(workspace.wrapped_dek_for_owner, privateKeyHandle);
                setWorkspaceDek(workspace.id, dekHandle);
            }

            const name = await decryptName(workspace.encrypted_name, dekHandle, workspace.name_iv);
            document.getElementById('workspace-name').textContent = name;
        })();
    </script>
@endpush
