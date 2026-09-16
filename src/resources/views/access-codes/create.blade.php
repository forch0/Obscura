@extends('layouts.app')

@section('title', 'New Access Code — Obscura')

@section('content')
    <div class="page-header">
        <h2>New Access Code</h2>
        <a href="{{ route('access-codes.index', $workspace) }}" class="btn-secondary">Cancel</a>
    </div>

    <form id="create-code-form" style="max-width:480px">
        @csrf
        <div class="form-group">
            <label>Scope</label>
            <select id="scope" class="form-input">
                <option value="workspace">Entire workspace</option>
                <option value="collection" disabled>Collection (Module 4)</option>
                <option value="gallery" disabled>Gallery (Module 4)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Permissions</label>
            <div style="display:flex;gap:16px;margin-top:8px">
                <label><input type="checkbox" id="perm-view" checked> View</label>
                <label><input type="checkbox" id="perm-upload"> Upload</label>
                <label><input type="checkbox" id="perm-comment"> Comment</label>
            </div>
        </div>
        <div class="form-group">
            <label>Duration</label>
            <select id="duration" class="form-input">
                <option value="60">1 hour</option>
                <option value="360">6 hours</option>
                <option value="1440">24 hours</option>
                <option value="10080">7 days</option>
                <option value="43200">30 days</option>
            </select>
        </div>
        <div class="form-group">
            <label>Max Uses (0 = unlimited)</label>
            <input type="number" id="max_uses" class="form-input" value="0" min="0">
        </div>
        <div class="form-group">
            <label>Label (optional)</label>
            <input type="text" id="label" class="form-input" placeholder="e.g., Sent to Alice">
        </div>
        <button type="submit" class="btn-primary" id="submit-btn">Generate Code</button>
        <p class="decrypt-status" id="status"></p>
    </form>
@endsection

@push('scripts')
    <script type="module">
        const form = document.getElementById('create-code-form');
        const statusEl = document.getElementById('status');
        const submitBtn = document.getElementById('submit-btn');
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

                // Step 1: Generate code on server
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

                // Step 2: Seal the workspace DEK with the code
                statusEl.innerHTML = '<span class="spinner"></span> Sealing DEK…';
                const { sealDekForCode } = await import('{{ Vite::asset("resources/js/crypto/code-key.js") }}');
                const { getWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');

                const dekHandle = getWorkspaceDek(workspaceId);
                if (!dekHandle) throw new Error('Workspace DEK not loaded. Please re-open the workspace first.');

                const wrappedDek = await sealDekForCode(dekHandle, raw_code, code_salt);

                // Step 3: Store wrapped DEK on server
                const dekResponse = await fetch(`/workspaces/${workspaceId}/access-codes/${code_id}/dek`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ wrapped_dek: wrappedDek }),
                });

                if (!dekResponse.ok) throw new Error('Failed to store sealed DEK');

                // Step 4: Show the code once
                statusEl.innerHTML = '';
                form.innerHTML = `
                    <div style="background:var(--accent-subtle);border:1px solid var(--accent);border-radius:12px;padding:24px;text-align:center">
                        <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:12px">Share this code — it won't be shown again</p>
                        <p style="font-size:1.5rem;font-weight:700;font-family:monospace;letter-spacing:0.05em">${raw_code}</p>
                        <button type="button" class="btn-primary" style="margin-top:16px" onclick="navigator.clipboard.writeText('${raw_code}');this.textContent='Copied!'">Copy Code</button>
                        <p style="font-size:0.8125rem;color:var(--text-secondary);margin-top:12px">Expires: ${new Date(Date.now() + ${parseInt(document.getElementById('duration')?.value || 1440)} * 60000).toLocaleString()}</p>
                    </div>
                `;
            } catch (e) {
                statusEl.textContent = 'Error: ' + e.message;
                submitBtn.disabled = false;
            }
        });
    </script>
@endpush
