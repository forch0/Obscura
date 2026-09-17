@extends('layouts.app')

@section('title', 'Workspace Members — Obscura')

@section('content')
    <p class="text-caption" style="margin-bottom:8px"><a href="{{ route('workspaces.show', $workspace) }}" style="color:hsl(var(--foreground));text-decoration:none">← Back to workspace</a></p>
    <div class="page-header keep-row">
        <div>
            <h2>Workspace Members</h2>
            <p class="text-caption text-secondary">Members can access this workspace's collections and galleries.</p>
        </div>
    </div>

    <div class="card" style="max-width:560px;margin-bottom:24px">
        <h3>Add Member</h3>
        <p class="text-caption text-secondary" style="margin-bottom:16px">Enter the email of the person to add. They must have registered and generated their keypair.</p>
        <form method="POST" action="{{ route('workspaces.members.store', $workspace) }}" id="add-member-form">
            @csrf
            <input type="hidden" name="user_id" id="user_id">
            <input type="hidden" name="wrapped_dek" id="wrapped_dek">
            <x-input label="Email" name="email" type="email" placeholder="their@email.com" required />
            <div class="form-group">
                <label class="form-label">Role</label>
                <x-select name="role" id="role" selected="viewer" :options="[
                    ['value' => 'viewer', 'label' => 'Viewer — can view media'],
                    ['value' => 'editor', 'label' => 'Editor — can upload/edit media'],
                ]" />
            </div>
            <x-button type="submit" variant="primary">Add Member</x-button>
            <p id="add-member-status" class="text-caption text-secondary" style="margin-top:8px"></p>
        </form>
    </div>

    <h3 style="margin-bottom:12px">Members ({{ $members->count() + 1 }})</h3>
    <div class="card" style="margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;padding:12px 16px">
        <div>
            <p class="font-medium">{{ $workspace->owner->email }}</p>
            <span class="badge badge-primary">Owner</span>
        </div>
    </div>
    @foreach($members as $member)
        <div class="card" style="margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;padding:12px 16px">
            <div>
                <p class="font-medium">{{ $member->user->email }}</p>
                <span class="badge {{ $member->role === 'editor' ? 'badge-secondary' : '' }}">{{ ucfirst($member->role) }}</span>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('workspaces.members.destroy', [$workspace, $member]) }}" style="display:inline" class="remove-member-form" data-member="{{ $member->user->email }}">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" size="sm">Remove</x-button>
                </form>
            </div>
        </div>
    @endforeach
@endsection

@push('scripts')
    <script type="module">
        const workspaceId = '{{ $workspace->id }}';
        const wrappedDekForOwner = @json($workspace->wrappedDekFor(auth()->user()));
        const lookupUrl = '{{ route('workspaces.members.lookup', $workspace) }}';
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const form = document.getElementById('add-member-form');
        const statusEl = document.getElementById('add-member-status');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const email = document.getElementById('email').value.trim();
            if (!email) {
                statusEl.textContent = 'Enter their email address.';
                return;
            }

            statusEl.innerHTML = '<span class="spinner"></span> Looking up user…';

            try {
                const res = await fetch(lookupUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ email }),
                });
                const data = await res.json();
                if (!res.ok) {
                    statusEl.textContent = data.error ?? 'Lookup failed.';
                    return;
                }

                statusEl.innerHTML = '<span class="spinner"></span> Wrapping workspace key…';

                const { unsealDek, importPublicKey } = await import('{{ Vite::asset("resources/js/crypto/dek.js") }}');
                const { restorePrivateKey } = await import('{{ Vite::asset("resources/js/crypto/session.js") }}');
                const { getWorkspaceDek, setWorkspaceDek } = await import('{{ Vite::asset("resources/js/crypto/workspace-session.js") }}');
                const { base64Encode } = await import('{{ Vite::asset("resources/js/crypto/pbkdf2.js") }}');

                let dek = getWorkspaceDek(workspaceId);
                if (!dek) {
                    const privateKeyHandle = await restorePrivateKey();
                    if (!privateKeyHandle) throw new Error('Private key not loaded.');
                    dek = await unsealDek(wrappedDekForOwner, privateKeyHandle);
                    setWorkspaceDek(workspaceId, dek);
                }

                const rawDek = await crypto.subtle.exportKey('raw', dek);
                const pub = await importPublicKey(data.public_key);
                const wrapped = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, pub, rawDek);

                document.getElementById('user_id').value = data.user_id;
                document.getElementById('wrapped_dek').value = base64Encode(new Uint8Array(wrapped));
                form.submit();
            } catch (err) {
                statusEl.textContent = 'Error: ' + err.message;
            }
        });
    </script>
@endpush
