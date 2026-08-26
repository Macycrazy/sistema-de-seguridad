<?php

namespace App\Console\Commands;

use App\Services\Carnets\FotoDelCarnet;
use App\Services\Carnets\PadronDelCarnet;
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

    public function handle(Rostros $rostros, FotoDelCarnet $fotos, PadronDelCarnet $padron): int
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

        /*
         * 4. Sin fotos no hay caras que mirar: es lo que más falla al estrenar.
         *
         * Y hay DOS vías, no una. Desde que el carnets sacó sus fotos de la carpeta pública, lo
         * normal es pedirlas por su API con un token; la carpeta o la URL directa siguen valiendo
         * para un carnets que aún no lo haya hecho. Basta con una de las dos, así que exigir la
         * vieja marcaría en rojo una instalación perfectamente correcta.
         */
        $token = trim((string) config('carnets.token'));
        $origen = trim((string) config('carnets.fotos'));
        $hayDeDonde = $token !== '' || $origen !== '';

        $problemas += $this->linea('Hay de dónde traer las fotos', $hayDeDonde,
            'Falta CARNETS_TOKEN (la API del carnets) o CARNETS_FOTOS (su carpeta o URL). Pon uno de los dos en el .env y corre «php artisan config:clear».');

        if ($hayDeDonde) {
            $this->line('       <fg=gray>Por '.($token !== '' ? 'la API del carnets (CARNETS_TOKEN)' : 'CARNETS_FOTOS: '.$origen).'</>');
        }

        /*
         * Por la API se puede preguntar mejor, y hay que hacerlo: probar tres personas al azar
         * confunde dos cosas que no se parecen —que el token esté mal puesto y que a esas tres no
         * les hayan cargado foto— y las dos salían como «no llegan».
         *
         * El padrón dice quién TIENE foto, así que primero se comprueba que responde (eso ya
         * descarta token e IP) y después se piden fotos de gente que debería tenerla.
         */
        if ($token !== '') {
            $this->newLine();

            $prueba = $padron->probar();
            $problemas += $this->linea('El carnets acepta este token', $prueba['ok'], $prueba['mensaje']);

            if ($prueba['ok']) {
                $conFoto = collect($padron->personal())->filter(fn ($f) => ($f['foto']['existe'] ?? false) === true);

                $this->line('       <fg=gray>'.$conFoto->count().' de '.($prueba['total'] ?? 0).' fichas del carnets tienen foto cargada</>');

                if ($conFoto->isEmpty()) {
                    $problemas++;
                    $this->line('       <fg=yellow>Ninguna ficha del carnets tiene foto: no hay caras que indexar hasta que las carguen allá.</>');
                } else {
                    $this->newLine();
                    $this->line('Probando a traer tres de las que SÍ tienen foto:');

                    $llegaron = 0;

                    foreach ($conFoto->take(3) as $ficha) {
                        $foto = $fotos->bytes((string) $ficha['cedula']);
                        $llegaron += $foto ? 1 : 0;

                        $this->line(sprintf('  %-28s %s',
                            mb_substr((string) ($ficha['nombre_completo'] ?? $ficha['cedula']), 0, 28),
                            $foto ? '<fg=green>llega ('.number_format(strlen($foto['bytes']) / 1024).' kB)</>' : '<fg=red>no llega</>',
                        ));
                    }

                    $problemas += $this->linea('Las fotos del carnets llegan', $llegaron > 0,
                        'El padrón dice que tienen foto pero no llegan: el carnets responde al token pero no sirve la imagen. Revisa allá con «php artisan seguridad:fotos».');
                }
            }
        } elseif ($hayDeDonde && $indexables->isNotEmpty()) {
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
                'Ninguna de las tres llegó. Revisa CARNETS_FOTOS: si es una carpeta, que exista y se pueda leer; si es una URL, que este servidor la alcance. Puede ser también que a esas tres no les hayan cargado foto.');
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
