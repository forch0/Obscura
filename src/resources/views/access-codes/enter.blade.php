@extends('layouts.auth')

@section('title', 'Enter Access Code — Obscura')
@section('card-title', 'Enter access code')
@section('card-subtitle', 'You\'ve been invited to view a private gallery')

@section('content')
    <form method="POST" action="{{ route('enter') }}">
        @csrf
        <x-input label="Access Code" name="code" :placeholder="'XXXX-XXXX-XXXX'" required style="font-family:'JetBrains Mono',ui-monospace,monospace;letter-spacing:0.05em;text-transform:uppercase" />
        <x-button type="submit" variant="primary" size="lg" pill class="w-full">Unlock</x-button>
    </form>
    <div style="text-align:center;margin-top:20px;font-size:0.875rem">
        <a href="{{ route('register') }}" class="text-secondary" style="text-decoration:none">Create an account</a>
    </div>
@endsection
