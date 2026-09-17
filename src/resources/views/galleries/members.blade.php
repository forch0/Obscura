@extends('layouts.app')

@section('title', 'Gallery Members — Obscura')

@section('content')
    <div class="page-header">
        <div>
            <p class="text-caption"><a href="{{ route('galleries.show', [$collection, $gallery]) }}" style="color:hsl(var(--foreground));text-decoration:none">← Back to gallery</a></p>
            <h2 style="margin-top:4px">Gallery Members</h2>
            <p class="text-caption"><span class="badge {{ $gallery->type === 'joint' ? 'badge-secondary' : 'badge-primary' }}">{{ ucfirst($gallery->type) }}</span></p>
        </div>
    </div>

    <div class="card" style="max-width:560px;margin-bottom:24px">
        <h3>Add Member</h3>
        <p class="text-caption text-secondary" style="margin-bottom:16px">Members must already be workspace members.</p>
        <form method="POST" action="{{ route('galleries.members.store', [$collection, $gallery]) }}">
            @csrf
            <x-input label="User ID" name="user_id" placeholder="UUID of workspace member" required />
            <div class="form-group">
                <label class="form-label">Role</label>
                <x-select name="role" id="role" selected="viewer" :options="collect([
                    ['value' => 'viewer', 'label' => 'Viewer — can view media'],
                    $gallery->isJoint() ? ['value' => 'editor', 'label' => 'Editor — can upload/edit media'] : null,
                ])->filter()->values()->all()" />
            </div>
            <x-button type="submit" variant="primary">Add Member</x-button>
        </form>
    </div>

    <h3 style="margin-bottom:12px">Current Members</h3>
    @forelse($members as $member)
        <div class="card" style="margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;padding:12px 16px">
            <div>
                <p class="font-medium">{{ $member->user->email }}</p>
                <span class="badge {{ $member->role === 'editor' ? 'badge-secondary' : '' }}">{{ ucfirst($member->role) }}</span>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('galleries.members.destroy', [$collection, $gallery, $member]) }}" style="display:inline" onsubmit="return confirm('Remove this member?')">
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
