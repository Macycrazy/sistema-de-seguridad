<?php

namespace App\Console\Commands;

use App\Services\Carnets\FotoDelCarnet;
use App\Services\Rostros\Rostros;
use Illuminate\Console\Command;

/**
 * Dice por qué el reconocimiento facial no hace nada.
 *
 * Son cuatro cosas y cualquiera de ellas lo deja mudo: que el navegador no tenga el código nuevo,
 * que no haya personal que indexar, que no se puedan leer las fotos del carnets, o que los modelos
 * no estén servidos. Desde la pantalla no se distinguen —el botón simplemente no responde—, así
 * que esto las comprueba una por una desde el servidor.
 */
class DiagnosticarRostros extends Command
{
    protected $signature = 'rostros:diagnostico';

    protected $description = 'Comprueba qué le falta al reconocimiento facial para funcionar';

    public function handle(Rostros $rostros, FotoDelCarnet $fotos): int
    {
        $this->newLine();
        $problemas = 0;

        // 1. El navegador no puede llamar a un código que no se compiló.
        $manifiesto = public_path('build/manifest.json');
        $compilado = is_file($manifiesto) && str_contains((string) file_get_contents($manifiesto), 'face-api');
        $problemas += $this->linea('El código del navegador está compilado', $compilado,
            'Falta: corre «npm install && npm run build». Sin esto el botón no llama a nada.');

        // 2. Los modelos se sirven desde este servidor: la red interna no sale a Internet.
        $modelos = ['tiny_face_detector_model.bin', 'face_landmark_68_tiny_model.bin', 'face_recognition_model.bin'];
        $faltan = array_filter($modelos, fn ($m) => ! is_file(public_path('modelos/rostros/'.$m)));
        $problemas += $this->linea('Los modelos están en el servidor', $faltan === [],
            'Faltan: '.implode(', ', $faltan).'. Vienen con el repo, revisa que el «git pull» los trajo.');

        // 3. Sin personal activo no hay a quién indexar, y el botón sale apagado con razón.
        $indexables = $rostros->indexables();
        $problemas += $this->linea('Hay personal que indexar', $indexables->isNotEmpty(),
            'No hay ningún trabajador activo. Solo se indexa al personal, no a los visitantes.');

        // 4. Sin fotos no hay caras que mirar: es lo que más falla al estrenar.
        $origen = trim((string) config('carnets.fotos'));
        $problemas += $this->linea('CARNETS_FOTOS está configurado', $origen !== '',
            'Vacío en el .env. Pon la carpeta o la URL de las fotos del carnets y corre «php artisan config:clear».');

        if ($origen !== '' && $indexables->isNotEmpty()) {
            $this->newLine();
            $this->line('Probando a traer fotos de verdad (las tres primeras):');

            $conFoto = 0;

            foreach ($indexables->take(3) as $persona) {
                $foto = $fotos->bytes($persona->cedula);
                $conFoto += $foto ? 1 : 0;

                $this->line(sprintf('  %-28s %s',
                    mb_substr($persona->nombre, 0, 28),
                    $foto ? '<fg=green>llega ('.number_format(strlen($foto['bytes']) / 1024).' kB)</>' : '<fg=red>sin foto</>',
                ));
            }

            $problemas += $this->linea('Las fotos del carnets llegan', $conFoto > 0,
                'Ninguna de las tres llegó. Revisa CARNETS_FOTOS: si es una carpeta, que exista y se pueda leer; si es una URL, que este servidor la alcance.');
        }

        $this->newLine();

        if ($problemas === 0) {
            $this->info('Todo en orden. Si el botón sigue sin responder, mira la consola del navegador (F12).');

            return self::SUCCESS;
        }

        $this->warn($problemas.' cosa(s) por resolver. Arregla lo marcado en rojo y vuelve a correr esto.');

        return self::FAILURE;
    }

    /** Devuelve 1 si es un problema, 0 si está bien. */
    private function linea(string $que, bool $bien, string $comoArreglarlo): int
    {
        $this->line(sprintf('  %s %s', $bien ? '<fg=green>OK  </>' : '<fg=red>MAL </>', $que));

        if (! $bien) {
            $this->line('       <fg=yellow>'.$comoArreglarlo.'</>');
        }

        return $bien ? 0 : 1;
    }
}
