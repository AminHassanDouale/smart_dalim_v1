<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use App\Services\CloudflareD1Service; // Add this import

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CloudflareD1Service::class, function ($app) {
            return new CloudflareD1Service();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (env('APP_ENV') == 'production') {
            URL::forceScheme('https');
        }
        Blade::anonymousComponentPath(resource_path('views/components'), '');

    }
}