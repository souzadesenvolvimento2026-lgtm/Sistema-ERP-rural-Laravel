<?php

namespace App\Providers;

use App\Support\FarmContext;
use Illuminate\Support\Facades\View;
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
        View::composer('*', function ($view) {
            if (!app()->runningInConsole() || app()->runningUnitTests()) {
                $view->with('property', app(FarmContext::class)->property());
            }
        });
    }
}
