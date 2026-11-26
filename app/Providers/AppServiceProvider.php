<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\Operativo;
use App\Models\TipoServicio;
use App\Policies\CatalogoPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        foreach ([Area::class, Departamento::class, TipoServicio::class, Operativo::class] as $modeloCatalogo) {
            Gate::policy($modeloCatalogo, CatalogoPolicy::class);
        }

        Event::listen(Login::class, function (Login $event): void {
            activity('autenticacion')
                ->causedBy($event->user)
                ->withProperties(['guard' => $event->guard])
                ->log('Inicio de sesión');
        });
    }
}
