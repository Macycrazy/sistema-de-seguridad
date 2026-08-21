<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Models\Movimiento;
use App\Models\Vehiculo;
use App\Models\VehiculoDeFlota;
use App\Models\VehiculoFijo;
use App\Models\VisitaEsperada;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vacía los datos del día a día para dejar el sistema como recién puesto, sin volver a cargarlo.
 *
 * Es para después de probar: quedan movimientos inventados, vehículos que no existen y estadías a
 * medias, y limpiarlos a mano desde la pantalla es imposible —el registro no se edita ni se borra
 * a propósito—.
 *
 * NO toca lo que costó cargar: personas, usuarios, roles y permisos, puestos, oficinas,
 * departamentos ni los ajustes. Eso se queda.
 *
 * Es destructivo y no tiene vuelta atrás, así que en seco por defecto: sin «--confirmar» solo
 * cuenta lo que borraría. Y en producción hace falta decirlo dos veces: ahí los movimientos son el
 * histórico de verdad, y para recortarlo por antigüedad está «registro:depurar», que archiva a un
 * CSV antes de borrar. Esto no archiva nada.
 */
class VaciarDatosDeOperacion extends Command
{
    protected $signature = 'sistema:vaciar
                            {--confirmar : Borra de verdad. Sin esto, solo cuenta lo que borraría.}
                            {--registro : Solo el registro de entradas y salidas.}
                            {--vehiculos : Solo los vehículos: estadías del estacionamiento y los de cada persona.}
                            {--flota : Solo el catálogo de vehículos de la empresa.}
                            {--visitas : Solo las visitas agendadas.}
                            {--con-bitacora : Incluye además la bitácora de auditoría (por omisión NO se toca).}
                            {--forzar-en-produccion : Hace falta para que corra con APP_ENV=production.}';

    protected $description = 'Vacía los datos de operación (registro, vehículos, flota, visitas) sin tocar personas ni configuración';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('forzar-en-produccion')) {
            $this->error('Esto es producción: aquí los movimientos son el histórico de verdad.');
            $this->line('Para recortarlo por antigüedad, y archivándolo antes, usa «registro:depurar».');
            $this->line('Si aun así quieres vaciar, añade --forzar-en-produccion.');

            return self::FAILURE;
        }

        $grupos = $this->grupos();
        $total = array_sum(array_column($grupos, 'cuantos'));

        $this->newLine();
        $this->line('<options=bold>Se vaciaría esto:</>');

        foreach ($grupos as $nombre => $grupo) {
            $this->line(sprintf('  %-38s <fg=yellow>%s</>', $nombre, number_format($grupo['cuantos'])));
        }

        $this->newLine();
        $this->line('<fg=green>Se queda todo lo demás</>: personas, usuarios, roles y permisos, puestos, oficinas, departamentos y ajustes.');
        $this->newLine();

        if ($total === 0) {
            $this->info('No hay nada que borrar.');

            return self::SUCCESS;
        }

        if (! $this->option('confirmar')) {
            $this->info('Es una simulación: no se ha tocado nada. Añade --confirmar para borrar las '.number_format($total).' filas.');

            return self::SUCCESS;
        }

        // Todo o nada: a medias quedarían estadías apuntando a una flota que ya no está.
        DB::transaction(function () use ($grupos) {
            foreach ($grupos as $grupo) {
                ($grupo['borrar'])();
            }
        });

        $this->info('Borradas '.number_format($total).' filas. El sistema queda como recién puesto.');

        return self::SUCCESS;
    }

    /**
     * Qué se vacía, en orden: primero lo que apunta a otras tablas.
     *
     * Sin ninguna opción se vacía todo (menos la bitácora, que hay que pedir aparte: es el rastro
     * de quién hizo qué, y borrarlo sin querer sería justo lo contrario de para lo que está).
     *
     * @return array<string, array{cuantos:int, borrar:callable}>
     */
    private function grupos(): array
    {
        $soloAlgunos = $this->option('registro') || $this->option('vehiculos')
            || $this->option('flota') || $this->option('visitas');

        $quiere = fn (string $opcion) => $soloAlgunos ? (bool) $this->option($opcion) : true;

        $grupos = [];

        if ($quiere('vehiculos')) {
            // Antes que la flota y que el registro: apunta a las dos.
            $grupos['Estadías del estacionamiento'] = [
                'cuantos' => VehiculoFijo::count(),
                'borrar' => fn () => VehiculoFijo::query()->delete(),
            ];
            $grupos['Vehículos de cada persona'] = [
                'cuantos' => Vehiculo::count(),
                'borrar' => fn () => Vehiculo::query()->delete(),
            ];
        }

        if ($quiere('flota')) {
            $grupos['Vehículos de la empresa (catálogo)'] = [
                'cuantos' => VehiculoDeFlota::count(),
                'borrar' => fn () => VehiculoDeFlota::query()->delete(),
            ];
        }

        if ($quiere('registro')) {
            $grupos['Entradas y salidas del registro'] = [
                'cuantos' => Movimiento::count(),
                'borrar' => fn () => Movimiento::query()->delete(),
            ];
        }

        if ($quiere('visitas')) {
            $grupos['Visitas agendadas'] = [
                'cuantos' => VisitaEsperada::count(),
                'borrar' => fn () => VisitaEsperada::query()->delete(),
            ];
        }

        if ($this->option('con-bitacora')) {
            $grupos['Bitácora de auditoría'] = [
                'cuantos' => Bitacora::count(),
                'borrar' => fn () => Bitacora::query()->delete(),
            ];
        }

        return $grupos;
    }
}
