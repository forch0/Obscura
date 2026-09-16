@extends('layouts.auth')

@section('title', 'Register — Obscura')

@section('content')
<form method="POST" action="{{ route('register') }}">
    @csrf
    <x-input label="Name" name="name" :placeholder="'Your name'" required :value="old('name')" />
    <x-input label="Email" name="email" type="email" :placeholder="'you@example.com'" required :value="old('email')" />
    <x-input label="Password" name="password" type="password" placeholder="Min 8 characters" required />
    <x-input label="Confirm Password" name="password_confirmation" type="password" placeholder="Confirm password" required />
    <x-button type="submit" variant="primary" size="lg" pill class="w-full">Create Account</x-button>
</form>
<div style="text-align:center;margin-top:20px;font-size:0.875rem">
    <span class="text-secondary">Already have an account?</span>
    <a href="{{ route('login') }}" style="color:hsl(var(--foreground));font-weight:500;text-decoration:none">Sign In</a>
</div>
@endsection
