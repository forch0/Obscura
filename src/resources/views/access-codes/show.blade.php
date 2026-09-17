@extends('layouts.app')

@section('title', 'Access Code â€” Obscura')

@section('content')
    <div class="page-header">
        <h2>Access Code Details</h2>
        <x-button variant="secondary" href="{{ route('access-codes.index', $code->workspace) }}">Back</x-button>
    </div>

    <div class="card" style="max-width:560px">
        @if($rawCode)
            <div style="margin-bottom:24px">
                <p class="text-caption text-secondary" style="margin-bottom:8px">Save this code â€” it won't be shown again</p>
                <x-code-display>{{ $rawCode }}</x-code-display>
            </div>
        @endif

        <h3>{{ $code->label ?? 'Untitled' }}</h3>
        <p class="text-caption text-secondary" style="margin-top:8px">
            Scope: {{ $code->scope }} Â&middot; Permissions: {{ implode(', ', $code->permissionNames()) }}
        </p>
        <p class="text-caption" style="margin-top:4px">
            Status: <span class="badge {{ $code->isRevoked() ? 'badge-destructive' : ($code->isExpired() ? 'badge-secondary' : 'badge-secondary') }}">{{ $code->isRevoked() ? 'Revoked' : ($code->isExpired() ? 'Expired' : 'Active') }}</span>
        </p>
        <p class="text-caption text-muted" style="margin-top:8px">
            Uses: {{ $code->use_count }}{{ $code->max_uses > 0 ? ' / ' . $code->max_uses : '' }} Â&middot; Expires: {{ $code->expires_at->format('M j, Y g:i A') }}
        </p>
    </div>
@endsection
