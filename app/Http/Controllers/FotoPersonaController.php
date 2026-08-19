<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Services\Auditoria\Auditoria;
use App\Services\Carnets\FotoDelCarnet;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega la foto de una persona.
 *
 * Las fotos NO están en una carpeta pública: viven en storage/app/private y solo salen por aquí.
 * Este controlador es el único portero, y por eso es donde va el permiso —el gate «ver-foto»— y
 * donde el bloque D anotará quién miró la cara de quién.
 *
 * Hoy el gate deja pasar a los tres roles: el vigilante necesita la foto para comprobar que quien
 * tiene delante es quien dice ser, y sin eso la pantalla de marcar no sirve para lo que sirve.
 * Está puesto igual, porque el día que alguien decida que un rol no debe verlas, se cambia en un
 * sitio y no en cinco.
 *
 * No lleva lógica: comprueba y delega, como pide el README.
 */
class FotoPersonaController extends Controller
{
    public function __invoke(Persona $persona): Response|StreamedResponse
    {
        Gate::authorize('ver-foto');

        $sinCache = ['Cache-Control' => 'private, no-store, max-age=0'];

        // Directo del carnets, que es la fuente: se pide en vivo por cédula, así se ve siempre la
        // foto actual y no una copia que se haya quedado vieja. El servidor la trae por dentro (no
        // el navegador), así que sirve aunque la página vaya por HTTPS y el carnets por HTTP.
        $directa = app(FotoDelCarnet::class)->bytes($persona->cedula);

        if ($directa !== null) {
            app(Auditoria::class)->vioFoto($persona);

            return response($directa['bytes'], 200, $sinCache + ['Content-Type' => $directa['mime']]);
        }

        // Respaldo: la copia que quedó al darlo de alta, por si el carnets no responde ahora.
        $ruta = $persona->rutaFotoSegura();

        abort_if($ruta === null, 404);

        // Quién miró la cara de quién queda anotado (el «bloque D» que este portero esperaba).
        // Con dedup: el navegador puede volver a pedir la imagen sin que sea otra mirada.
        app(Auditoria::class)->vioFoto($persona);

        return Storage::disk('local')->response($ruta, headers: $sinCache);
    }
}
