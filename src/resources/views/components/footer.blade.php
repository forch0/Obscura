{{-- Shared footer — used by public, app, and auth layouts --}}
<footer class="site-footer">
    <div class="site-footer-links">
        <a href="{{ route('home') }}">Obscura</a>
        <a href="{{ route('about') }}">About</a>
        <a href="{{ route('architecture') }}">Architecture</a>
        <a href="{{ route('use-cases') }}">Use Cases</a>
        <a href="{{ route('showcase') }}">Case Study</a>
        <a href="{{ route('enter') }}">Enter Code</a>
    </div>
    <p class="site-footer-copy">&copy; {{ date('Y') }} Obscura. All rights reserved.</p>
</footer>
