@extends('layouts.app')

@section('title', 'New Access Code — Obscura')

@section('content')
    <div class="page-header">
        <h2>New Access Code</h2>
        <x-button variant="secondary" href="{{ route('access-codes.index', $workspace) }}">Cancel</x-button>
    </div>

    <div class="card" style="max-width:560px">
        <form id="create-code-form">
            @csrf
            <div class="form-group">
                <label class="form-label">Scope</label>
                <x-select name="scope" id="scope" selected="workspace" :options="[
                    'workspace' => 'Entire workspace',
                    'collection' => 'Collection',
                    'gallery' => 'Gallery',
                ]" />
            </div>
            <div class="form-group" id="target-group" style="display:none">
                <label class="form-label" id="target-label">Collection</label>
                <div class="dropdown select-dropdown">
                    <button type="button" class="form-input select-toggle dropdown-toggle" aria-haspopup="listbox" aria-expanded="false">
                        <span class="select-value" id="target-value">Decrypting…</span>
                        <span class="caret">&#9662;</span>
                    </button>
                    <input type="hidden" name="scope_id" id="scope_id" value="">
                    <div class="dropdown-menu select-menu" role="listbox" id="target-menu"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Permissions</label>
                <div class="flex gap-4" style="margin-top:8px">
                    <label><input type="checkbox" id="perm-view" checked> View</label>
                    <label><input type="checkbox" id="perm-upload"> Upload</label>
                    <label><input type="checkbox" id="perm-comment"> Comment</label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Duration</label>
                <x-select name="duration" id="duration" selected="1440" :options="[
                    '60' => '1 hour',
                    '360' => '6 hours',
                    '1440' => '24 hours',
                    '10080' => '7 days',
                    '43200' => '30 days',
                ]" />
            </div>
            <x-input label="Max Uses (0 = unlimited)" name="max_uses" type="number" :placeholder="0" />
            <x-input label="Recipient Email (optional)" name="recipient_email" type="email" placeholder="person@example.com" />
            <x-input label="Label (optional)" name="label" placeholder="e.g., Sent to Alice" />
            <x-button type="submit" variant="primary" size="lg" pill class="w-full">Generate Code</x-button>
            <p class="decrypt-status" id="status" style="margin-top:12px"></p>
        </form>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const form = document.getElementById('create-code-form');
        const statusEl = document.getElementById('status');
        const submitBtn = form.querySelector('button[type=submit]');
        const workspaceId = '{{ $workspace->id }}';
        const collections = @json($collections);

        let dekHandle = null;

        // Load DEK early so we can decrypt collection/gallery names for the scope picker
        (async () => {
            try {
                const { unsealDek } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
                const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
                const { getWorkspaceDek, setWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');
                const { decryptName } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');

                const privateKeyHandle = await restorePrivateKey();
                if (!privateKeyHandle) return;

                dekHandle = getWorkspaceDek(workspaceId) || await unsealDek(@json($workspace->wrappedDekFor(auth()->user())), privateKeyHandle);
                setWorkspaceDek(workspaceId, dekHandle);

                // Decrypt all collection + gallery names
                for (const c of collections) {
                    c.name = await decryptName(c.encrypted_name, dekHandle, c.name_iv);
                    for (const g of c.galleries) {
                        g.name = await decryptName(g.encrypted_name, dekHandle, g.name_iv);
                    }
                }

                document.getElementById('target-value').textContent = 'Select…';
            } catch (e) {
                document.getElementById('target-value').textContent = 'Could not decrypt names';
            }
        })();

        // Show/hide + populate the target picker when scope changes
        document.getElementById('scope').addEventListener('change', (e) => {
            const scope = e.target.value;
            const group = document.getElementById('target-group');
            const label = document.getElementById('target-label');
            const menu = document.getElementById('target-menu');
            const input = document.getElementById('scope_id');
            const value = document.getElementById('target-value');

            if (scope === 'workspace') {
                group.style.display = 'none';
                input.value = '';
                return;
            }

            group.style.display = '';
            input.value = '';
            value.textContent = 'Select…';
            label.textContent = scope === 'collection' ? 'Collection' : 'Gallery';

            const items = scope === 'collection'
                ? collections.map(c => ({ id: c.id, name: c.name }))
                : collections.flatMap(c => c.galleries.map(g => ({ id: g.id, name: `${c.name} / ${g.name}` })));

            menu.innerHTML = items.map(i =>
                `<button type="button" class="dropdown-item" data-value="${i.id}" role="option" aria-selected="false">${i.name ?? '(unnamed)'}</button>`
            ).join('') || `<div style="padding:10px 16px;font-size:0.875rem;color:hsl(var(--muted-foreground))">No ${scope}s in this workspace</div>`;
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitBtn.disabled = true;
            statusEl.innerHTML = '<span class="spinner"></span> Generating code…';

            try {
                const scope = document.getElementById('scope').value;
                const scopeId = document.getElementById('scope_id').value || null;
                if (scope !== 'workspace' && !scopeId) {
                    throw new Error(`Select a ${scope} first.`);
                }

                const permissions =
                    (document.getElementById('perm-view').checked ? 1 : 0) +
                    (document.getElementById('perm-upload').checked ? 2 : 0) +
                    (document.getElementById('perm-comment').checked ? 4 : 0);

                const response = await fetch(`/workspaces/${workspaceId}/access-codes`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        scope,
                        scope_id: scopeId,
                        permissions,
                        duration_minutes: parseInt(document.getElementById('duration').value),
                        max_uses: parseInt(document.getElementById('max_uses').value) || 0,
                        label: document.getElementById('label').value || null,
                        recipient_email: document.getElementById('recipient_email').value || null,
                    }),
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || 'Failed to generate code');
                }
                const { code_id, raw_code, code_salt } = await response.json();

                statusEl.innerHTML = '<span class="spinner"></span> Sealing DEK…';
                const { sealDekForCode } = await import('{{ Vite::asset("resources/js/crypto/code-key.js") }}');
                const { unsealDek } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
                const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
                const { getWorkspaceDek, setWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

                if (!dekHandle) {
                    const privateKeyHandle = await restorePrivateKey();
                    if (!privateKeyHandle) throw new Error('Private key not loaded.');
                    dekHandle = getWorkspaceDek(workspaceId) || await unsealDek(@json($workspace->wrappedDekFor(auth()->user())), privateKeyHandle);
                    setWorkspaceDek(workspaceId, dekHandle);
                }

                const wrappedDek = await sealDekForCode(dekHandle, raw_code, code_salt);

                const dekRes = await fetch(`/workspaces/${workspaceId}/access-codes/${code_id}/dek`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ wrapped_dek: wrappedDek }),
                });

                if (!dekRes.ok) throw new Error('Failed to store sealed DEK');

                statusEl.innerHTML = '';
                const expiryLabel = new Date(Date.now() + (parseInt(document.getElementById('duration')?.value || 1440) * 60000)).toLocaleString();
                form.innerHTML = `
                    <div style="text-align:center;padding:24px">
                        <p class="text-caption text-secondary" style="margin-bottom:12px">Share this code — it won't be shown again</p>
                        <div class="code-display">${raw_code}</div>
                        <div style="margin-top:16px">
                            <button type="button" class="btn btn-primary btn-pill" onclick="navigator.clipboard.writeText('${raw_code}');this.textContent='Copied!'">Copy Code</button>
                        </div>
                        <p class="text-caption text-muted" style="margin-top:12px">Expires: ${expiryLabel}</p>
                    </div>
                `;
            } catch (e) {
                statusEl.textContent = 'Error: ' + e.message;
                submitBtn.disabled = false;
            }
        });
    </script>
@endpush
