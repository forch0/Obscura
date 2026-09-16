@extends('layouts.auth')

@section('title', 'Recovery Code — Obscura')
@section('subtitle', 'Save your recovery code')

@section('extra-styles')
<style>
    .recovery-code-display {
        background: hsl(var(--muted));
        border: 1px solid hsl(var(--border));
        border-radius: 12px;
        padding: 24px;
        text-align: center;
        margin: 24px 0;
    }
    .recovery-code-display code {
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 1.25rem;
        font-weight: 500;
        letter-spacing: 0.08em;
        color: hsl(var(--foreground));
        word-break: break-all;
    }
    .recovery-warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 0.8125rem;
        margin-bottom: 20px;
        line-height: 1.5;
    }
    .recovery-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }
    .recovery-actions button { flex: 1; }
    .recovery-actions .btn-primary { flex: 2; }
    .no-code {
        text-align: center;
        color: hsl(var(--muted-foreground));
        padding: 40px 0;
    }
</style>
@endsection

@section('content')
<div id="recovery-code-app">
    <div id="has-code" style="display:none;">
        <div class="recovery-warning">
            <strong>Important:</strong> This code is shown only once. Print it or write it down now.
            If you forget your password, you will need this code to recover your encrypted data.
        </div>

        <div class="recovery-code-display">
            <code id="recovery-code-value"></code>
        </div>

        <div class="recovery-actions">
            <button class="btn-secondary" onclick="window.print()">Print</button>
            <form method="GET" action="{{ route('home') }}" style="flex:2;">
                <button type="submit" class="btn btn-primary btn-lg w-full">I've saved it — continue</button>
            </form>
        </div>
    </div>

    <div id="no-code" class="no-code" style="display:none;">
        <p>No recovery code found. It may have already been displayed.</p>
        <div style="text-align:center;margin-top:20px;font-size:0.875rem">
            <a href="{{ route('home') }}">Continue to home</a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var code = sessionStorage.getItem('recovery_code');
    if (code) {
        document.getElementById('recovery-code-value').textContent = code;
        document.getElementById('has-code').style.display = 'block';
        sessionStorage.removeItem('recovery_code');
    } else {
        document.getElementById('no-code').style.display = 'block';
    }
})();
</script>
@endpush
