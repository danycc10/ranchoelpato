<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // El agente sondea la cola cada pocos segundos; el límite es por clave
        // de agente, no por IP, porque varias cajas pueden salir por la misma.
        RateLimiter::for('print-agent', function (Request $request): Limit {
            $credential = $request->bearerToken() ?: $request->ip();

            return Limit::perMinute(120)->by(hash('sha256', (string) $credential));
        });
    }
}
