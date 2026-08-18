<?php

namespace App\Console\Commands;

use App\Services\Auditoria\Auditoria;
use App\Services\Retencion\Depuracion;
use Illuminate\Console\Command;

/**
 * Archiva y depura los datos viejos según la política de retención de Ajustes.
 *
 * Es la única vía por la que el sistema borra histórico, y a propósito NO se programa sola: la
 * corre una persona (o un cron que el administrador decida poner). Sin «--confirmar» solo enseña
 * lo que haría; con «--confirmar» archiva a un CSV y borra. Si los periodos están en 0, no hace
 * nada aunque se ejecute.
 */
class DepurarRegistro extends Command
{
    protected $signature = 'registro:depurar
                            {--confirmar : Archiva y borra de verdad. Sin esto, solo muestra lo que haría.}';

    protected $description = 'Archiva y depura movimientos y bitácora más viejos que la política de retención';

    public function handle(Depuracion $depuracion, Auditoria $auditoria): int
    {
        if (! $depuracion->estaActiva()) {
            $this->warn('La depuración está desactivada: los dos periodos están en 0.');
            $this->line('Fíjalos en Ajustes → Retención de datos para poder depurar.');

            return self::SUCCESS;
        }

        if (! $this->option('confirmar')) {
            $this->mostrar('Se depuraría esto (simulación):', $depuracion->plan());
            $this->newLine();
            $this->info('Es una simulación: no se ha tocado nada. Añade --confirmar para archivar y borrar.');

            return self::SUCCESS;
        }

        $informe = $depuracion->ejecutar();
        $this->mostrar('Depurado:', $informe, conArchivo: true);

        $detalle = collect($informe)
            ->filter(fn ($f) => $f['cuantos'] > 0)
            ->map(fn ($f) => $f['tabla'].': '.$f['cuantos'].' (antes de '.$f['corte']->format('Y-m-d').')')
            ->implode(' · ');

        if ($detalle !== '') {
            $auditoria->depuroDatos($detalle);
            $this->info('Registrado en la bitácora.');
        } else {
            $this->line('No había nada más viejo que el corte. Nada que borrar.');
        }

        return self::SUCCESS;
    }

    /** @param array<int, array<string, mixed>> $informe */
    private function mostrar(string $titulo, array $informe, bool $conArchivo = false): void
    {
        $this->info($titulo);

        $filas = collect($informe)->map(function ($f) use ($conArchivo) {
            $fila = [
                $f['tabla'],
                $f['meses'] > 0 ? $f['meses'].' meses' : 'desactivado',
                $f['corte']?->format('Y-m-d') ?? '—',
                $f['cuantos'],
            ];

            if ($conArchivo) {
                $fila[] = $f['archivo'] ?? '—';
            }

            return $fila;
        })->all();

        $cabecera = ['Tabla', 'Retención', 'Corte', 'Registros'];
        if ($conArchivo) {
            $cabecera[] = 'Archivo';
        }

        $this->table($cabecera, $filas);
    }
}
