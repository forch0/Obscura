@extends('layouts.auth')

@section('title', 'Sign In — Obscura')
@section('subtitle', 'Private encrypted gallery')

@section('content')
<form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="form-group">
        <label for="email">Email</label>
        <input id="email" class="form-input" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com">
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input id="password" class="form-input" type="password" name="password" required placeholder="Your password">
    </div>
    <div class="form-group" style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="remember" id="remember" style="width:16px;height:16px;">
        <label for="remember" style="margin:0;font-size:0.875rem;color:hsl(var(--muted-foreground));">Remember me</label>
    </div>
    <div class="form-group">
        <button type="submit" class="btn-primary">Sign In</button>
    </div>
</form>
<div class="auth-links">
    Don't have an account? <a href="{{ route('register') }}">Register</a><br>
    <a href="{{ route('recover') }}">Forgot password?</a>
</div>
@endsection
