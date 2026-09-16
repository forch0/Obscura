@extends('layouts.app')

@section('title', 'Re-key Workspace — Obscura')

@section('content')
    <div class="page-header">
        <h2>Re-key Workspace</h2>
        <x-button variant="secondary" href="{{ route('workspaces.show', $workspace) }}">Cancel</x-button>
    </div>

    <div class="card" style="max-width:560px">
        <h3 style="color:var(--danger)">Warning</h3>
        <p class="text-secondary">Re-keying generates a new encryption key for this workspace. This will:</p>
        <ul style="margin:12px 0;padding-left:20px;color:var(--text-secondary);font-size:0.875rem;list-style:disc">
            <li><strong>Invalidate all access codes</strong> — code holders will need new codes</li>
            <li><strong>Re-wrap all media keys</strong> — file contents are not re-encrypted, only the key wraps</li>
            <li><strong>Re-encrypt all names/descriptions</strong> — collections, galleries, media titles</li>
            <li><strong>Keep member access</strong> — members get the new key automatically</li>
        </ul>
        <p class="text-caption text-muted">Current DEK version: {{ $workspace->dek_version }}</p>

        @if($activeJob)
            <div style="margin-top:16px;padding:12px;background:var(--accent-subtle);border-radius:8px">
                <p class="text-sm">A re-key is already in progress ({{ $activeJob->processed_media }}/{{ $activeJob->total_media }} media).</p>
            </div>
        @else
            <x-button variant="danger" id="start-rekey" style="margin-top:16px">Start Re-key</x-button>
        @endif

        <div id="rekey-progress" style="display:none;margin-top:16px">
            <p id="rekey-status" class="decrypt-status"></p>
            <x-progress-bar id="rekey-bar" :percent="0" style="margin-top:8px" />
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const workspaceId = '{{ $workspace->id }}';
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const btn = document.getElementById('start-rekey');
        const progress = document.getElementById('rekey-progress');
        const statusEl = document.getElementById('rekey-status');
        const bar = document.querySelector('#rekey-bar .fill');

        btn?.addEventListener('click', async () => {
            if (!confirm('This will invalidate all access codes. Continue?')) return;

            btn.disabled = true;
            progress.style.display = '';
            statusEl.innerHTML = '<span class="spinner"></span> Starting re-key…';

            try {
                const res = await fetch(`/workspaces/${workspaceId}/rekey`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });

                if (res.status === 409) {
                    statusEl.textContent = 'A re-key is already in progress.';
                    return;
                }
                if (!res.ok) throw new Error('Failed to initiate re-key');
                const data = await res.json();

                statusEl.innerHTML = '<span class="spinner"></span> Generating new DEK…';
                const { startRekey } = await import('{{ Vite::asset("resources/js/crypto/rekey.js") }}');
                const { getPrivateKeyHandle } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
                const { getWorkspaceDek, setWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

                const oldDek = getWorkspaceDek(workspaceId);
                if (!oldDek) throw new Error('Workspace DEK not loaded.');

                const result = await startRekey(oldDek, data);

                statusEl.innerHTML = '<span class="spinner"></span> Applying changes…';
                bar.style.width = '50%';

                const completeRes = await fetch(`/workspaces/${workspaceId}/rekey/${data.job_id}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        new_wrapped_dek_for_owner: result.new_wrapped_dek_for_owner,
                        member_wraps: result.member_wraps,
                        media_wraps: result.media_wraps,
                        collection_wraps: result.collection_wraps,
                        gallery_wraps: result.gallery_wraps,
                    }),
                });

                if (!completeRes.ok) throw new Error('Failed to complete re-key');
                const done = await completeRes.json();

                setWorkspaceDek(workspaceId, result.newDekHandle);

                bar.style.width = '100%';
                statusEl.innerHTML = `<p style="color:var(--accent)">Re-key complete. DEK version: ${done.dek_version}. ${done.codes_revoked} access codes revoked.</p>`;
            } catch (e) {
                statusEl.textContent = 'Error: ' + e.message;
                btn.disabled = false;
            }
        });
    </script>
@endpush
