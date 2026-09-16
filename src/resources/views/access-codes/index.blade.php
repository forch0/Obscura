@extends('layouts.app')

@section('title', 'Access Codes — Obscura')

@section('content')
    <div class="page-header">
        <h2>Access Codes</h2>
        <a href="{{ route('access-codes.create', $workspace) }}" class="btn-primary">New Code</a>
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
            list.innerHTML = '<div class="empty-state"><h3>No access codes</h3><p>Generate a code to share this workspace.</p></div>';
        } else {
            const grid = document.createElement('div');
            grid.className = 'workspace-grid';

            for (const c of codes) {
                const isRevoked = c.revoked_at !== null;
                const isExpired = new Date(c.expires_at) < new Date();
                const status = isRevoked ? 'Revoked' : isExpired ? 'Expired' : 'Active';

                const card = document.createElement('div');
                card.className = 'workspace-card';
                card.innerHTML = `
                    <h3>${c.label || 'Untitled'}</h3>
                    <p>${c.scope} — ${c.permission_names.join(', ')}</p>
                    <p>${status} | Uses: ${c.use_count}${c.max_uses > 0 ? ' / ' + c.max_uses : ''}</p>
                    <p>Expires: ${new Date(c.expires_at).toLocaleString()}</p>
                    ${!isRevoked && !isExpired ? `
                        <div class="actions">
                            <form method="POST" action="/workspaces/${workspaceId}/access-codes/${c.id}" style="display:inline">
                                <input type="hidden" name="_method" value="DELETE">
                                <input type="hidden" name="_token" value="${document.querySelector('meta[name=csrf-token]').content}">
                                <button type="submit" class="btn-danger" onclick="return confirm('Revoke this code?')">Revoke</button>
                            </form>
                        </div>
                    ` : ''}
                `;
                grid.appendChild(card);
            }

            list.innerHTML = '';
            list.appendChild(grid);
        }
    </script>
@endpush
