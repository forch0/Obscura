@extends('layouts.app')

@section('title', 'Workspace — Obscura')

@section('content')
    <div class="page-header">
        <h2 id="workspace-name"><span class="spinner"></span> Decrypting…</h2>
        <div class="flex gap-2" style="flex-wrap:wrap">
            <x-button variant="secondary" size="sm" href="{{ route('collections.index', $workspace) }}">Collections</x-button>
            <x-button variant="secondary" size="sm" href="{{ route('access-codes.index', $workspace) }}">Codes</x-button>
            <x-dropdown label="Manage" variant="secondary" size="sm" align="right">
                <x-dropdown.item href="{{ route('workspaces.edit', $workspace) }}">Rename</x-dropdown.item>
                <x-dropdown.item href="{{ route('workspaces.rekey', $workspace) }}">Re-key</x-dropdown.item>
                <x-dropdown.separator />
                <x-dropdown.item danger type="submit" form="delete-workspace-form">Delete workspace</x-dropdown.item>
            </x-dropdown>
            <form id="delete-workspace-form" method="POST" action="{{ route('workspaces.destroy', $workspace) }}" style="display:none" onsubmit="return confirm('Delete this workspace? All collections and galleries will be permanently lost.')">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    <div id="workspace-content">
        <div class="card">
            <div class="ws-stats">
                <div>
                    <p class="text-caption text-muted">DEK Version</p>
                    <p class="font-semibold">{{ $workspace->dek_version }}</p>
                </div>
                @if ($workspace->rekeyed_at)
                    <div>
                        <p class="text-caption text-muted">Last Re-keyed</p>
                        <p class="font-medium">{{ $workspace->rekeyed_at->format('M j, Y g:i A') }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-caption text-muted">Collections</p>
                    <p class="font-semibold">{{ $workspace->collections->count() }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .ws-stats { display: flex; gap: 24px; flex-wrap: wrap; }
    @media (max-width: 480px) {
        .ws-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    }
</style>
@endpush

@push('scripts')
    @php
        $wsData = array_merge(
            $workspace->only(['id', 'encrypted_name', 'name_iv']),
            ['wrapped_dek' => $workspace->wrappedDekFor(auth()->user())]
        );
    @endphp
    <script type="module">
        const workspace = @json($wsData);

        (async () => {
            const { unsealDek, decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
            const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
            const { setWorkspaceDek, hasWorkspaceDek, getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

            const privateKeyHandle = await restorePrivateKey();
            if (!privateKeyHandle) {
                document.getElementById('workspace-name').textContent = 'Private key not loaded';
                return;
            }

            let dekHandle;
            if (hasWorkspaceDek(workspace.id)) {
                dekHandle = getWorkspaceDek(workspace.id);
            } else {
                dekHandle = await unsealDek(workspace.wrapped_dek, privateKeyHandle);
                setWorkspaceDek(workspace.id, dekHandle);
            }

            const name = await decryptName(workspace.encrypted_name, dekHandle, workspace.name_iv);
            document.getElementById('workspace-name').textContent = name;
        })();
    </script>
@endpush
