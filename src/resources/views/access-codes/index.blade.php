@extends('layouts.app')

@section('title', 'Access Codes — Obscura')

@section('content')
    <div class="page-header keep-row">
        <h2>Access Codes</h2>
        <x-button variant="primary" size="md" pill href="{{ route('access-codes.create', $workspace) }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" width="14" height="14" style="vertical-align:-2px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Code</x-button>
    </div>

    <div id="codes-list">
        <div class="decrypt-status"><span class="spinner"></span> Loading…</div>
    </div>
@endsection

@push('scripts')
    @php
        $codesData = $codes->map(fn($c) => [
            'id' => $c->id,
            'scope' => $c->scope,
            'permissions' => $c->permissions,
            'label' => $c->label,
            'recipient_email' => $c->recipient_email,
            'expires_at' => $c->expires_at->toISOString(),
            'revoked_at' => $c->revoked_at?->toISOString(),
            'use_count' => $c->use_count,
            'max_uses' => $c->max_uses,
            'permission_names' => $c->permissionNames(),
        ]);
    @endphp
    <script type="module">
        const codes = @json($codesData);
        const workspaceId = '{{ $workspace->id }}';
        const list = document.getElementById('codes-list');

        if (codes.length === 0) {
            list.innerHTML = '<div class="empty-state"><h3>No access codes</h3><p class="text-secondary">Generate a code to share this workspace.</p></div>';
        } else {
            for (const c of codes) {
                const isRevoked = c.revoked_at !== null;
                const isExpired = new Date(c.expires_at) < new Date();
                const status = isRevoked ? 'Revoked' : isExpired ? 'Expired' : 'Active';
                const statusClass = isRevoked ? 'badge-destructive' : isExpired ? 'badge-secondary' : 'badge-secondary';
                const opacity = isRevoked || isExpired ? 'opacity:0.5' : '';

                const card = document.createElement('div');
                card.className = 'card';
                card.style.cssText = opacity + ';margin-bottom:12px';
                card.innerHTML = `
                    <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:12px">
                        <div>
                            <h3 style="margin-bottom:4px">${c.label || 'Untitled'}</h3>
                            ${c.recipient_email ? `<p class="text-caption" style="margin-bottom:4px">Shared with: <strong>${c.recipient_email}</strong></p>` : ''}
                            <p class="text-caption text-secondary">${c.scope} �&middot; ${c.permission_names.join(' �&middot; ')}</p>
                            <p class="text-caption text-muted">Uses: ${c.use_count}${c.max_uses > 0 ? ' / ' + c.max_uses : ''} �&middot; Expires: ${new Date(c.expires_at).toLocaleString()}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge ${statusClass}">${status}</span>
                            ${!isRevoked && !isExpired ? `
                                <form method="POST" action="/workspaces/${workspaceId}/access-codes/${c.id}" style="display:inline">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <input type="hidden" name="_token" value="${document.querySelector('meta[name=csrf-token]').content}">
                                    <button type="button" class="btn btn-danger btn-sm" data-revoke="${c.id}">Revoke</button>
                                </form>
                            ` : ''}
                        </div>
                    </div>
                `;
                list.appendChild(card);
            }

            // Delegate revoke clicks → custom confirm
            list.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-revoke]');
                if (!btn) return;
                const ok = await ObscuraDialog.confirmDialog({
                    title: 'Revoke access code',
                    message: 'This code will stop working immediately. Anyone using it will lose access.',
                    confirmLabel: 'Revoke',
                    danger: true,
                });
                if (ok) btn.closest('form').submit();
            });
        }
    </script>
@endpush
