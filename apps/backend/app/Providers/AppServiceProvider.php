<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('sensitive', fn (Request $request) => [
            Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perHour(100)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())),
        ]);

        RateLimiter::for('payment-webhook', fn (Request $request) => [
            Limit::perMinute(120)->by($request->ip()),
            Limit::perHour(5000)->by($request->ip()),
        ]);
    }
}
