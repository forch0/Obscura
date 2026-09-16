@extends('layouts.auth')

@section('title', 'Verify Email — Obscura')
@section('subtitle', 'Confirm your email address')

@section('content')
@if (session('resent'))
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:12px 16px;border-radius:8px;font-size:0.875rem;margin-bottom:20px;">
        Verification link sent! Check your inbox.
    </div>
@endif

<div style="text-align:center;padding:20px 0;">
    <p style="color:hsl(var(--muted-foreground));font-size:0.9375rem;line-height:1.6;margin-bottom:24px;">
        We've sent a verification link to your email address.<br>
        Please click the link to verify your account.
    </p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-lg w-full">Resend Verification Email</button>
        </div>
    </form>

    <div style="text-align:center;margin-top:20px;font-size:0.875rem">
        <a href="{{ route('home') }}">Continue to Obscura</a>
    </div>
</div>
@endsection
