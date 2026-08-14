<?php

namespace App\Http\Controllers;

use App\Models\Persona;
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
    public function __invoke(Persona $persona): StreamedResponse
    {
        Gate::authorize('ver-foto');

        $ruta = $persona->rutaFotoSegura();

        abort_if($ruta === null, 404);

        return Storage::disk('local')->response($ruta, headers: [
            // La cara de una persona no se queda en la caché de un proxy ni del navegador.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
