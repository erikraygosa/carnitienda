<?php

namespace App\Providers;

use App\Services\CompanyService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton — misma instancia en todo el request
        $this->app->singleton(CompanyService::class);
    }

    public function boot(): void
    {
        // Inyectar $empresaActiva en TODAS las vistas automáticamente
        View::composer('*', function ($view) {
            $service = app(CompanyService::class);
            $view->with('empresaActiva', $service->activa());
        });
    }
}