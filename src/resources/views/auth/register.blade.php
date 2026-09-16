@extends('layouts.auth')

@section('title', 'Register — Obscura')
@section('subtitle', 'Create your account')

@section('content')
<form method="POST" action="{{ route('register') }}">
    @csrf
    <div class="form-group">
        <label for="name">Name</label>
        <input id="name" class="form-input" type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="Your name">
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input id="email" class="form-input" type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com">
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input id="password" class="form-input" type="password" name="password" required placeholder="Create a password">
    </div>
    <div class="form-group">
        <label for="password_confirmation">Confirm Password</label>
        <input id="password_confirmation" class="form-input" type="password" name="password_confirmation" required placeholder="Repeat password">
    </div>
    <div class="form-group">
        <button type="submit" class="btn-primary">Create Account</button>
    </div>
</form>
<div class="auth-links">
    Already have an account? <a href="{{ route('login') }}">Sign in</a>
</div>
@endsection
