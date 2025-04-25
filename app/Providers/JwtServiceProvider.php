<?php

namespace App\Providers;

use App\Providers\Service\JwtService;
use Illuminate\Support\ServiceProvider;


class JwtServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        $this->app->singleton(JwtService::class, function ($app) {
            return new JwtService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
