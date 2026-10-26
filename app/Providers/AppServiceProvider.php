<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Customer lockout is by rate limiter only — no lockout columns are
        // added to customers (owner decision). Admin lockout lives server-side
        // on the admins row (see AuthService).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('place-order', fn (Request $request) => Limit::perMinute(15)->by(
            $request->user('customer')?->customer_id ?? $request->ip()
        ));
    }
}
