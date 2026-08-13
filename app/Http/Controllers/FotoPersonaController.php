<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega la foto de una persona.
 *
 * Las fotos NO están en una carpeta pública: viven en storage/app/private y solo salen por aquí.
 * Este controlador es el único portero, y por eso es el sitio donde la parte 3 tiene que poner
 * el permiso por rol y el rastro de quién miró la cara de quién. Mientras eso no exista, sirve
 * la foto a quien la pida, igual que el resto de la parte 1.
 *
 * No lleva lógica: comprueba y delega, como pide el README.
 */
class FotoPersonaController extends Controller
{
    public function __invoke(Persona $persona): StreamedResponse
    {
        $ruta = $persona->rutaFotoSegura();

        abort_if($ruta === null, 404);

        return Storage::disk('local')->response($ruta, headers: [
            // La cara de una persona no se queda en la caché de un proxy ni del navegador.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
