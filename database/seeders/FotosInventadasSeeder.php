<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Fotos de mentira para desarrollo, generadas aquí mismo con GD.
 *
 * Son un color plano con las iniciales, y se ve a la legua que no son fotos reales: eso es
 * intencionado. Las de verdad vienen del sistema de carnets, cuyo enlace no forma parte de estas
 * tres partes, y **fotos reales de personas no se copian a la máquina de nadie**.
 *
 * Se generan en vez de venir en el repositorio para no versionar imágenes binarias y para que
 * funcione en la máquina de cualquiera sin descargar nada.
 *
 * Solo se le pone foto a algunos trabajadores, para poder ver los dos casos en la pantalla:
 * con foto y con las iniciales de respaldo.
 */
class FotosInventadasSeeder extends Seeder
{
    /** Uno de cada dos, para que en la pantalla se vean los dos casos. */
    private const CADA_CUANTOS = 2;

    public function run(): void
    {
        if (! extension_loaded('gd')) {
            $this->command?->warn('Sin la extensión gd no se pueden generar las fotos de prueba.');

            return;
        }

        $trabajadores = Persona::where('tipo', Persona::TRABAJADOR)->orderBy('cedula')->get();

        foreach ($trabajadores->values() as $i => $trabajador) {
            if ($i % self::CADA_CUANTOS !== 0) {
                continue;
            }

            $ruta = Persona::CARPETA_FOTOS."/{$trabajador->cedula}.jpg";

            Storage::disk('local')->put($ruta, $this->dibujar($trabajador));

            $trabajador->update(['foto_ruta' => $ruta]);
        }
    }

    /**
     * Un cuadrado de color con las iniciales.
     *
     * Se dibuja pequeño y se agranda sin suavizar: las letras salen con los bordes marcados, y
     * así se distinguen de una foto de verdad a primera vista. No se usa una tipografía del
     * sistema a propósito, porque el servidor donde esto va a correr no es esta máquina.
     */
    private function dibujar(Persona $persona): string
    {
        $lado = 60;
        $lienzo = imagecreatetruecolor($lado, $lado);

        // El color sale de la cédula: la misma persona siempre tiene el mismo, y dos personas
        // distintas casi nunca coinciden.
        $semilla = (int) $persona->cedula;
        $fondo = imagecolorallocate(
            $lienzo,
            120 + ($semilla % 90),
            120 + (intdiv($semilla, 90) % 90),
            120 + (intdiv($semilla, 8100) % 90),
        );
        imagefilledrectangle($lienzo, 0, 0, $lado, $lado, $fondo);

        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        $iniciales = $persona->iniciales();

        // La fuente 5 de GD mide 9x15 px; se centra a mano.
        $ancho = imagefontwidth(5) * strlen($iniciales);
        imagestring($lienzo, 5, intdiv($lado - $ancho, 2), intdiv($lado - imagefontheight(5), 2), $iniciales, $blanco);

        $grande = imagescale($lienzo, 240, 240, IMG_NEAREST_NEIGHBOUR);
        imagedestroy($lienzo);

        ob_start();
        imagejpeg($grande, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($grande);

        return $jpeg;
    }
}
