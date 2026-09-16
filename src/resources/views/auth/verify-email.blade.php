@extends('layouts.auth')

@section('title', 'Verify Email — Obscura')
@section('card-title', 'Check your email')
@section('card-subtitle', 'Confirm your email address')

@section('content')
@if (session('resent'))
    <div style="background:hsl(var(--primary)/0.06);border:1px solid hsl(var(--border));color:hsl(var(--foreground));padding:12px 16px;border-radius:8px;font-size:0.875rem;margin-bottom:20px">
        Verification link sent — check your inbox.
    </div>
@endif

<p class="text-secondary" style="font-size:0.9375rem;line-height:1.6;margin-bottom:24px">
    We've sent a verification link to your email address.
    Please click the link to verify your account.
</p>

<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="btn btn-primary btn-lg w-full">Resend Verification Email</button>
</form>

<div style="text-align:center;margin-top:20px;font-size:0.875rem">
    <a href="{{ route('home') }}" class="text-secondary" style="text-decoration:none">Continue to Obscura</a>
</div>
@endsection
