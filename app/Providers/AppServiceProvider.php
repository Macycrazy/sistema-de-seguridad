<?php

namespace App\Providers;

use App\Services\Registro\FuenteDelRegistro;
use App\Services\Registro\RegistroInventado;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // De dónde saca sus datos el registro (parte 2).
        //
        // Hoy son datos inventados, porque el esquema de las tablas todavía no está
        // acordado entre las tres partes. Cuando lo esté, se escribe la implementación
        // contra la base de datos y se cambia solo esta línea: ni el componente Livewire
        // ni las vistas ni las pruebas se enteran.
        //
        // Singleton a propósito: generar el juego de datos cuesta, y así se hace una vez
        // por request en vez de una vez por cada sitio que lo pida.
        $this->app->singleton(FuenteDelRegistro::class, RegistroInventado::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
