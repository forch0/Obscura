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
                <label for="scope" class="form-label">Scope</label>
                <select id="scope" class="form-input">
                    <option value="workspace">Entire workspace</option>
                    <option value="collection" disabled>Collection (Module 4)</option>
                    <option value="gallery" disabled>Gallery (Module 4)</option>
                </select>
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
                <label for="duration" class="form-label">Duration</label>
                <select id="duration" class="form-input">
                    <option value="60">1 hour</option>
                    <option value="360">6 hours</option>
                    <option value="1440" selected>24 hours</option>
                    <option value="10080">7 days</option>
                    <option value="43200">30 days</option>
                </select>
            </div>
            <x-input label="Max Uses (0 = unlimited)" name="max_uses" type="number" :placeholder="0" />
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

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitBtn.disabled = true;
            statusEl.innerHTML = '<span class="spinner"></span> Generating code…';

            try {
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
                        scope: document.getElementById('scope').value,
                        permissions,
                        duration_minutes: parseInt(document.getElementById('duration').value),
                        max_uses: parseInt(document.getElementById('max_uses').value) || 0,
                        label: document.getElementById('label').value || null,
                    }),
                });

                if (!response.ok) throw new Error('Failed to generate code');
                const { code_id, raw_code, code_salt } = await response.json();

                statusEl.innerHTML = '<span class="spinner"></span> Sealing DEK…';
                const { sealDekForCode } = await import('{{ Vite::asset("resources/js/crypto/code-key.js") }}');
                const { getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

                const dekHandle = getWorkspaceDek(workspaceId);
                if (!dekHandle) throw new Error('Workspace DEK not loaded.');

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
                form.innerHTML = `
                    <div style="text-align:center;padding:24px">
                        <p class="text-caption text-secondary" style="margin-bottom:12px">Share this code — it won't be shown again</p>
                        <div class="code-display">${raw_code}</div>
                        <div style="margin-top:16px">
                            <button type="button" class="btn btn-primary btn-pill" onclick="navigator.clipboard.writeText('${raw_code}');this.textContent='Copied!'">Copy Code</button>
                        </div>
                        <p class="text-caption text-muted" style="margin-top:12px">Expires: ${new Date(Date.now() + ${parseInt(document.getElementById('duration')?.value || 1440)} * 60000).toLocaleString()}</p>
                    </div>
                `;
            } catch (e) {
                statusEl.textContent = 'Error: ' + e.message;
                submitBtn.disabled = false;
            }
        });
    </script>
@endpush
