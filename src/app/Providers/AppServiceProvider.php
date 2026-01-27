<?php

namespace App\Providers;

use App\Auth\JwtGuard;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        URL::forceScheme('https');

        Auth::extend('jwt', function ($app, $name, array $config) {
            return new JwtGuard($app['request']);
        });
    }
}
