@extends('layouts.auth')

@section('title', 'Enter Access Code — Obscura')

@section('content')
    <p style="text-align:center;font-size:0.875rem;color:var(--text-secondary);margin-bottom:16px">You've been invited to view a private gallery</p>
    <form method="POST" action="{{ route('enter') }}">
        @csrf
        <div class="form-group">
            <label for="code" class="form-label">Access Code</label>
            <input type="text" id="code" name="code" class="form-input mono" placeholder="XXXX-XXXX-XXXX" required style="letter-spacing:0.05em">
            @error('code')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <x-button type="submit" variant="primary" size="lg" pill class="w-full">Unlock</x-button>
    </form>
    <p style="text-align:center;margin-top:16px;font-size:0.875rem">
        <a href="{{ route('register') }}" style="color:var(--accent);text-decoration:none">Create an account</a>
    </p>
@endsection
