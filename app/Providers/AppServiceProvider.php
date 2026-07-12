<?php

namespace App\Providers;

use App\Domain\Access\ProfileAccess;
use App\Domain\Finance\FinancialMetrics;
use App\Domain\Geo\PolygonGeometry;
use App\Domain\Production\ContractRules;
use App\Support\FarmContext;
use App\View\Composers\FarmfortLayoutComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProfileAccess::class);
        $this->app->singleton(FinancialMetrics::class);
        $this->app->singleton(PolygonGeometry::class);
        $this->app->singleton(ContractRules::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (! app()->runningInConsole() || app()->runningUnitTests()) {
                $view->with('property', app(FarmContext::class)->property());
            }
        });

        View::composer('layouts.farmfort', FarmfortLayoutComposer::class);
    }
}
