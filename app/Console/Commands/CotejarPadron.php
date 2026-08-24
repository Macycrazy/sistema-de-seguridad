<?php

namespace App\Console\Commands;

use App\Services\Carnets\CotejoConCarnets;
use Illuminate\Console\Command;

/**
 * Dice qué personal del carnets no está en el sistema de seguridad, y al revés.
 *
 * Las dos listas se llevan por separado y se separan solas: entra alguien, lo dan de alta en
 * carnets, aquí nadie lo carga, y el día que llega no aparece en la puerta. Esto lo saca a la luz
 * antes de que pase.
 *
 * Solo INFORMA. Dar de alta a alguien es una decisión de nómina, no algo que deba ocurrir solo
 * porque dos listas no coincidan.
 */
class CotejarPadron extends Command
{
    protected $signature = 'padron:cotejar
                            {--faltantes : Solo los que están en carnets y no aquí.}';

    protected $description = 'Compara el personal activo del carnets con el del sistema de seguridad';

    public function handle(CotejoConCarnets $cotejo): int
    {
        $resultado = $cotejo->comparar();

        if (! $resultado['disponible']) {
            $this->error('No se pudo consultar el padrón del carnets.');
            $this->line('Revisa CARNETS_TOKEN en el .env y que este servidor alcance el carnets.');
            $this->line('Con «php artisan rostros:diagnostico» se ve qué falla.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(sprintf(
            '  En carnets (activos): <fg=yellow>%d</>   ·   Aquí: <fg=yellow>%d</>   ·   Coinciden: <fg=green>%d</>',
            $resultado['enCarnets'],
            $resultado['aqui'],
            $resultado['coinciden'],
        ));
        $this->newLine();

        $faltan = $resultado['faltan'];

        if ($faltan->isEmpty()) {
            $this->info('No falta nadie: todo el personal activo del carnets está aquí.');
        } else {
            $this->warn($faltan->count().' persona(s) están activas en carnets y NO en este sistema:');
            $this->line('  <fg=gray>Se plantarán en la puerta y no aparecerán. Se cargan desde Trabajadores.</>');
            $this->newLine();

            foreach ($faltan as $ficha) {
                $this->line(sprintf('  %-12s %-38s %s',
                    $ficha['cedula'],
                    mb_substr($ficha['nombre'], 0, 38),
                    $ficha['gerencia'] ?? '',
                ));
            }
        }

        if ($this->option('faltantes')) {
            return $faltan->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();

        // Existen aquí pero desactivados: tampoco pueden marcar, pero se reactivan, no se cargan.
        if ($resultado['desactivados']->isNotEmpty()) {
            $this->warn($resultado['desactivados']->count().' persona(s) están desactivadas aquí y activas en carnets:');
            $this->line('  <fg=gray>Se reactivan desde Trabajadores: su ficha y su histórico ya están.</>');

            foreach ($resultado['desactivados'] as $persona) {
                $this->line(sprintf('  %-12s %s', $persona->cedula, mb_substr((string) $persona->nombre, 0, 38)));
            }

            $this->newLine();
        }

        // El carnets es solo del CIIP: de los otros dos entes no está nadie allá, y es lo normal.
        if ($resultado['otrosEntes'] > 0) {
            $this->line('  <fg=gray>'.$resultado['otrosEntes'].' de Marca País y VENAPP quedan fuera: el carnets es solo del CIIP.</>');
        }

        if ($resultado['sinEnte']->isNotEmpty()) {
            $this->warn($resultado['sinEnte']->count().' persona(s) sin ente asignado y sin carnet:');
            $this->line('  <fg=gray>No se puede saber si les falta el carnet o es que no son del CIIP.</>');

            foreach ($resultado['sinEnte'] as $persona) {
                $this->line(sprintf('  %-12s %s', $persona->cedula, mb_substr((string) $persona->nombre, 0, 38)));
            }

            $this->newLine();
        }

        $sobran = $resultado['sobran'];

        if ($sobran->isEmpty()) {
            $this->info('Del CIIP no sobra nadie: todos los activos siguen activos en carnets.');
        } else {
            $this->warn($sobran->count().' persona(s) del CIIP están activas aquí y ya NO en carnets:');
            $this->line('  <fg=gray>Puede que se hayan ido. Su histórico se conserva aunque se desactiven.</>');
            $this->newLine();

            foreach ($sobran as $persona) {
                $this->line(sprintf('  %-12s %s', $persona->cedula, mb_substr((string) $persona->nombre, 0, 38)));
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
