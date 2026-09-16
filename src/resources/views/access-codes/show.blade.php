@extends('layouts.app')

@section('title', 'Access Code — Obscura')

@section('content')
    <div class="page-header">
        <h2>Access Code Details</h2>
        <a href="{{ route('access-codes.index', $code->workspace) }}" class="btn-secondary">Back</a>
    </div>

    <div class="workspace-card" style="max-width:480px">
        @if($rawCode)
            <div style="background:var(--accent-subtle);border:1px solid var(--accent);border-radius:12px;padding:24px;text-align:center;margin-bottom:24px">
                <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:8px">Save this code — it won't be shown again</p>
                <p style="font-size:1.75rem;font-weight:700;font-family:monospace;letter-spacing:0.05em">{{ $rawCode }}</p>
            </div>
        @endif

        <h3>{{ $code->label ?? 'Untitled' }}</h3>
        <p>Scope: {{ $code->scope }}</p>
        <p>Permissions: {{ implode(', ', $code->permissionNames()) }}</p>
        <p>Status: {{ $code->isRevoked() ? 'Revoked' : ($code->isExpired() ? 'Expired' : 'Active') }}</p>
        <p>Uses: {{ $code->use_count }}{{ $code->max_uses > 0 ? ' / ' . $code->max_uses : '' }}</p>
        <p>Expires: {{ $code->expires_at->format('M j, Y g:i A') }}</p>
    </div>
@endsection
