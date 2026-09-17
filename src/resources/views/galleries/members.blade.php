@extends('layouts.app')

@section('title', 'Gallery Members — Obscura')

@section('content')
    <p class="text-caption" style="margin-bottom:8px"><a href="{{ route('galleries.show', [$collection, $gallery]) }}" style="color:hsl(var(--foreground));text-decoration:none">← Back to gallery</a></p>
    <div class="page-header keep-row">
        <div>
            <h2>Gallery Members</h2>
            <p class="text-caption"><span class="badge {{ $gallery->type === 'joint' ? 'badge-secondary' : 'badge-primary' }}">{{ ucfirst($gallery->type) }}</span></p>
        </div>
    </div>

    <div class="card" style="max-width:560px;margin-bottom:24px">
        <h3>Add Member</h3>
        <p class="text-caption text-secondary" style="margin-bottom:16px">Members must already be workspace members.</p>
        @if($eligible->isNotEmpty())
        <form method="POST" action="{{ route('galleries.members.store', [$collection, $gallery]) }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Workspace Member</label>
                <x-select name="user_id" id="user_id" :options="$eligible->map(fn($m) => [
                    'value' => $m->user_id,
                    'label' => $m->user->email,
                ])->all()" />
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <x-select name="role" id="role" selected="viewer" :options="collect([
                    ['value' => 'viewer', 'label' => 'Viewer — can view media'],
                    $gallery->isJoint() ? ['value' => 'editor', 'label' => 'Editor — can upload/edit media'] : null,
                ])->filter()->values()->all()" />
            </div>
            <x-button type="submit" variant="primary">Add Member</x-button>
        </form>
        @else
            <x-empty-state title="No eligible members" message="All workspace members are already in this gallery — or add workspace members first." />
        @endif
    </div>

    <h3 style="margin-bottom:12px">Current Members</h3>
    @forelse($members as $member)
        <div class="card" style="margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;padding:12px 16px">
            <div>
                <p class="font-medium">{{ $member->user->email }}</p>
                <span class="badge {{ $member->role === 'editor' ? 'badge-secondary' : '' }}">{{ ucfirst($member->role) }}</span>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('galleries.members.destroy', [$collection, $gallery, $member]) }}" style="display:inline" class="remove-member-form" data-member="{{ $member->user->email }}">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" size="sm">Remove</x-button>
                </form>
            </div>
        </div>
    @empty
        <x-empty-state title="No members" message="Add workspace members to collaborate on this gallery." />
    @endforelse
@endsection

@push('scripts')
<script type="module">
    document.querySelectorAll('.remove-member-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const ok = await ObscuraDialog.confirmDialog({
                title: 'Remove member',
                message: `Remove ${form.dataset.member} from this gallery?`,
                confirmLabel: 'Remove',
                danger: true,
            });
            if (ok) form.submit();
        });
    });
</script>
@endpush
