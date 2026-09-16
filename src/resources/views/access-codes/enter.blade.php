@extends('layouts.auth')

@section('title', 'Enter Access Code — Obscura')

@section('content')
    <form method="POST" action="{{ route('enter') }}">
        @csrf
        <div class="form-group">
            <label for="code">Access Code</label>
            <input type="text" id="code" name="code" class="form-input" placeholder="XXXX-XXXX-XXXX" style="font-family:monospace;letter-spacing:0.05em" required>
            @error('code')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-primary">Enter</button>
    </form>
@endsection
