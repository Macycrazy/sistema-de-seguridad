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
     * Lee la foto de esa cédula EN VIVO del carnets, sin guardarla. Es lo que usa la pantalla para
     * mostrar siempre la foto directo de la fuente, no una copia que se pueda quedar vieja.
     *
     * @return array{bytes:string, mime:string}|null
     */
    public function bytes(string $cedula): ?array
    {
        $cedula = Persona::normalizarCedula($cedula);
        $origen = trim((string) config('carnets.fotos'));

        if ($cedula === '' || $origen === '') {
            return null;
        }

        foreach (self::EXTENSIONES as $extension) {
            $bytes = $this->leer($origen, $cedula, $extension);

            if ($bytes !== null && $bytes !== '') {
                return [
                    'bytes' => $bytes,
                    'mime' => $extension === 'png' ? 'image/png' : 'image/jpeg',
                ];
            }
        }

        return null;
    }

    /**
     * Deja la foto de esa cédula en el disco privado y devuelve su ruta («fotos/12345678.jpg»),
     * o null si no se pudo traer. La copia local queda como respaldo por si el carnets no responde.
     */
    public function traer(string $cedula): ?string
    {
        $foto = $this->bytes($cedula);

        if ($foto === null) {
            return null;
        }

        $extension = $foto['mime'] === 'image/png' ? 'png' : 'jpg';
        $ruta = Persona::CARPETA_FOTOS.'/'.Persona::normalizarCedula($cedula).'.'.$extension;
        Storage::disk('local')->put($ruta, $foto['bytes']);

        return $ruta;
    }

    /** Lee los bytes de la foto, del disco o por HTTP según cómo esté configurado el origen. */
    private function leer(string $origen, string $cedula, string $extension): ?string
    {
        $nombre = $cedula.'.'.$extension;

        if (str_starts_with($origen, 'http://') || str_starts_with($origen, 'https://')) {
            try {
                $peticion = Http::timeout((int) config('carnets.timeout', 4));

                // Con token, la petición va firmada. Hace falta para la ruta autenticada del
                // padrón; a la carpeta pública de siempre no le estorba una cabecera de más, así
                // que se manda igual y no hay que distinguir de qué origen se trata.
                if ($token = trim((string) config('carnets.token'))) {
                    $peticion = $peticion->withHeaders(['X-API-Token' => $token]);
                }

                $respuesta = $peticion->get(rtrim($origen, '/').'/'.$nombre);
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
