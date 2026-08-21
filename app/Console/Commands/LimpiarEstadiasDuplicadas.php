<?php

namespace App\Console\Commands;

use App\Models\VehiculoFijo;
use App\Services\Auditoria\Auditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Busca vehículos que figuran dentro más de una vez y cierra las estadías que sobran.
 *
 * Un vehículo no puede estar dentro dos veces, y al anotarlo ya se impide. Pero los duplicados de
 * antes de esa regla siguen ahí, y dan la cara así: se le marca la salida al carro, se actualiza
 * la pantalla, y el carro sigue apareciendo dentro —porque se cerró una estadía y quedó la otra—.
 * Además ocupan plaza y cuentan en el aforo.
 *
 * Sacar el vehículo ya cierra sus duplicados, así que esto es para limpiar de una vez lo que se
 * arrastra, sin esperar a que cada carro salga.
 *
 * Como todo lo que toca el histórico, en seco por defecto: sin «--confirmar» solo enseña lo que
 * haría. Se queda SIEMPRE la estadía más reciente de cada placa, que es la que refleja dónde está
 * el vehículo ahora.
 */
class LimpiarEstadiasDuplicadas extends Command
{
    protected $signature = 'estacionamiento:duplicados
                            {--confirmar : Cierra de verdad las estadías que sobran. Sin esto, solo muestra lo que haría.}';

    protected $description = 'Encuentra vehículos anotados dentro más de una vez y cierra las estadías que sobran';

    public function handle(Auditoria $auditoria): int
    {
        $duplicados = $this->duplicados();

        if ($duplicados->isEmpty()) {
            $this->info('No hay ningún vehículo anotado dentro más de una vez.');

            return self::SUCCESS;
        }

        $this->warn($duplicados->count().' vehículo(s) figuran dentro más de una vez:');
        $this->newLine();

        $sobran = collect();

        foreach ($duplicados as $placa => $estadias) {
            // La más reciente se queda: es la que dice dónde está el vehículo ahora.
            $sequeda = $estadias->first();
            $aCerrar = $estadias->slice(1);
            $sobran = $sobran->concat($aCerrar);

            $this->line("  <fg=yellow>{$placa}</> · {$estadias->count()} estadías abiertas");
            $this->line('    se queda: entró el '.$sequeda->entro_en->format('d/m/Y g:i a')
                .($sequeda->puesto ? ' · puesto '.$sequeda->puesto->codigo : ' · sin puesto'));

            foreach ($aCerrar as $otra) {
                $this->line('    <fg=red>se cierra</>: entró el '.$otra->entro_en->format('d/m/Y g:i a')
                    .($otra->puesto ? ' · puesto '.$otra->puesto->codigo : ' · sin puesto'));
            }
        }

        $this->newLine();

        if (! $this->option('confirmar')) {
            $this->info('Es una simulación: no se ha tocado nada. Añade --confirmar para cerrar las '.$sobran->count().' que sobran.');

            return self::SUCCESS;
        }

        foreach ($sobran as $estadia) {
            $estadia->update([
                'salio_en' => now(),
                // Sin conductor ni usuario: no se lo llevó nadie, es una fila que sobraba. Poner un
                // nombre ahí sería inventar una salida que no ocurrió.
                'nota' => trim(($estadia->nota ? $estadia->nota.' · ' : '').'Cerrada por duplicada'),
            ]);

            $auditoria->cerroEstadiaDuplicada($estadia);
        }

        $this->info('Cerradas '.$sobran->count().' estadías duplicadas. Queda una por vehículo.');

        return self::SUCCESS;
    }

    /**
     * Las placas con más de una estadía abierta, cada una con las suyas de la más reciente a la
     * más antigua.
     *
     * @return Collection<string, Collection<int, VehiculoFijo>>
     */
    private function duplicados(): Collection
    {
        return VehiculoFijo::query()
            ->abiertos()
            ->with('puesto')
            ->orderByDesc('entro_en')
            ->orderByDesc('id')
            ->get()
            ->groupBy('placa')
            ->filter(fn (Collection $estadias) => $estadias->count() > 1);
    }
}
