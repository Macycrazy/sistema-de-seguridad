<?php

use App\Http\Middleware\ExigirUsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás de un proxy HTTPS local (Caddy) que termina el TLS y reenvía por HTTP al puerto
        // interno. Sin confiar en él, Laravel vería «http» y generaría enlaces/Livewire/assets en
        // http dentro de una página https —contenido mixto que el navegador bloquea, y sin lo cual
        // la cámara (que exige HTTPS) no serviría de nada—. Se confía solo en el proxy local.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        // Sin sesión no se ve nada del sistema: se va derecho a la puerta. Y quien ya entró no
        // vuelve a ver la pantalla de ingreso.
        $middleware->redirectGuestsTo('/ingresar');
        $middleware->redirectUsersTo('/');

        // Desactivar a un usuario tiene que echarlo ya, no cuando se le ocurra salir.
        $middleware->web(append: [
            ExigirUsuarioActivo::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
