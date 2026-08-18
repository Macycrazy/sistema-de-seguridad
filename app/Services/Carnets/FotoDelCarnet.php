<?php

namespace App\Services\Carnets;

use App\Models\Persona;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Trae del sistema de carnets la foto de un trabajador y la guarda en el disco privado.
 *
 * El carnets ya tiene una foto por cédula. En vez de volver a pedirla, se copia de ahí al dar de
 * alta a alguien. El origen se configura en config/carnets.php y puede ser una carpeta (misma
 * máquina) o una URL (carnets en otro servidor de la red); este servicio no distingue para quien
 * lo llama: le pide la foto de una cédula y devuelve dónde quedó, o null.
 *
 * Es best-effort a propósito: si el carnets no responde, o esa persona no tiene foto, se devuelve
 * null y el trabajador se da de alta igual. Traer la foto no puede tumbar la carga de la nómina.
 */
class FotoDelCarnet
{
    /** Extensiones que se prueban, en orden. La primera que exista gana. */
    private const EXTENSIONES = ['jpg', 'jpeg', 'png'];

    /**
     * Deja la foto de esa cédula en el disco privado y devuelve su ruta («fotos/12345678.jpg»),
     * o null si no se pudo traer.
     */
    public function traer(string $cedula): ?string
    {
        $cedula = Persona::normalizarCedula($cedula);
        $origen = trim((string) config('carnets.fotos'));

        if ($cedula === '' || $origen === '') {
            return null;
        }

        foreach (self::EXTENSIONES as $extension) {
            $bytes = $this->leer($origen, $cedula, $extension);

            if ($bytes !== null && $bytes !== '') {
                $ruta = Persona::CARPETA_FOTOS.'/'.$cedula.'.'.$extension;
                Storage::disk('local')->put($ruta, $bytes);

                return $ruta;
            }
        }

        return null;
    }

    /** Lee los bytes de la foto, del disco o por HTTP según cómo esté configurado el origen. */
    private function leer(string $origen, string $cedula, string $extension): ?string
    {
        $nombre = $cedula.'.'.$extension;

        if (str_starts_with($origen, 'http://') || str_starts_with($origen, 'https://')) {
            try {
                $respuesta = Http::timeout((int) config('carnets.timeout', 4))
                    ->get(rtrim($origen, '/').'/'.$nombre);
            } catch (\Throwable) {
                // Carnets caído o sin ruta: se sigue sin foto.
                return null;
            }

            return $respuesta->successful() ? $respuesta->body() : null;
        }

        // Una carpeta en el disco. Se comprueba que la ruta final quede DENTRO del origen, por si
        // una cédula trajera «../»: normalizarCedula ya deja solo dígitos, pero la defensa se pone
        // igual, que es barata.
        $archivo = rtrim($origen, '/').'/'.$nombre;

        if (! is_file($archivo)) {
            return null;
        }

        $contenido = @file_get_contents($archivo);

        return $contenido === false ? null : $contenido;
    }
}
