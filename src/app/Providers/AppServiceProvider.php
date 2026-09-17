<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auth endpoints — per-IP + credential-targeted
        RateLimiter::for('login', fn (Request $r) =>
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')) . '|' . $r->ip()));

        RateLimiter::for('register', fn (Request $r) =>
            Limit::perMinute(3)->by($r->ip()));

        RateLimiter::for('recover', fn (Request $r) =>
            Limit::perMinute(5)->by($r->ip()));

        RateLimiter::for('email', fn (Request $r) =>
            Limit::perMinute(6)->by($r->user()?->id ?: $r->ip()));

        // Access-code entry — the brute-force target; codes are only 12 chars
        RateLimiter::for('code-entry', fn (Request $r) =>
            Limit::perMinute(10)->by($r->ip()));

        // Guest code-holder browsing — generous but bounded
        RateLimiter::for('access-view', fn (Request $r) =>
            Limit::perMinute(120)->by($r->ip()));

        // Owner operations
        RateLimiter::for('code-generate', fn (Request $r) =>
            Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));

        RateLimiter::for('media-upload', fn (Request $r) =>
            Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));

        RateLimiter::for('media-read', fn (Request $r) =>
            Limit::perMinute(240)->by($r->user()?->id ?: $r->ip()));

        RateLimiter::for('rekey', fn (Request $r) =>
            Limit::perMinute(6)->by($r->user()?->id ?: $r->ip()));

        RateLimiter::for('keypair', fn (Request $r) =>
            Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));

        // General authenticated writes (workspaces, collections, galleries, members)
        RateLimiter::for('writes', fn (Request $r) =>
            Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
    }
}
