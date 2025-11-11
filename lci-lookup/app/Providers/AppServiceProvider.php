<?php

namespace App\Providers;

use App\Auth\SessionUserProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;

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
        Vite::prefetch(concurrency: 3);

        // Register custom session user provider
        Auth::provider('session', function ($app, array $config) {
            return new SessionUserProvider();
        });
    }
}
