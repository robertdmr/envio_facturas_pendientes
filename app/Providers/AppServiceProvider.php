<?php

namespace App\Providers;

use App\Services\PuntopanConexion;
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
        // El banner del layout necesita saber si hay una copia local aprobada.
        // Se resuelve al renderizar (no en cada request) para no hacer trabajo
        // innecesario cuando la vista no se usa.
        View::composer('layouts.app', function ($view): void {
            $view->with('puntopanConexion', app(PuntopanConexion::class));
        });
    }
}
