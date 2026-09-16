@extends('layouts.auth')

@section('title', 'Sign In — Obscura')

@section('content')
<form method="POST" action="{{ route('login') }}">
    @csrf
    <x-input label="Email" name="email" type="email" :placeholder="'you@example.com'" required value="{{ old('email') }}" autofocus />
    <x-input label="Password" name="password" type="password" placeholder="Your password" required />
    <div class="form-group" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="remember" id="remember" style="width:16px;height:16px">
        <label for="remember" class="text-caption" style="margin:0;cursor:pointer">Remember me</label>
    </div>
    <x-button type="submit" variant="primary" size="lg" pill class="w-full">Sign In</x-button>
</form>
<div style="text-align:center;margin-top:20px;font-size:0.875rem">
    <span class="text-secondary">Don't have an account?</span>
    <a href="{{ route('register') }}" style="color:hsl(var(--foreground));font-weight:500;text-decoration:none">Register</a><br>
    <a href="{{ route('recover') }}" class="text-caption" style="text-decoration:none;display:inline-block;margin-top:8px">Forgot password?</a>
</div>
@endsection
