<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\BookingCreated;
use App\Events\BookingConfirmed;
use App\Events\BookingCancelled;
use App\Listeners\ClearBookingCache;
use App\Listeners\SendBookingNotification;

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
        // إجبار النظام على استخدام HTTPS (لحل مشكلة Mixed Content)
        if (config('app.env') !== 'local') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Register Booking Listeners
        Event::listen(
            [BookingCreated::class, BookingConfirmed::class, BookingCancelled::class],
            ClearBookingCache::class
        );

        Event::listen(
            [BookingCreated::class, BookingConfirmed::class, BookingCancelled::class],
            SendBookingNotification::class
        );

        // Rate Limiters
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('booking', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
