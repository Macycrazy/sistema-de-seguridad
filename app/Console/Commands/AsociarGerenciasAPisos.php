<?php

namespace App\Console\Commands;

use App\Services\Edificio\AsociadorDeGerencias;
use Illuminate\Console\Command;

/**
 * Precarga la asociación piso → gerencia en el catálogo del edificio, a partir del personal ya
 * cargado. Es de arranque: se corre una vez (o cada vez que entre mucha gente nueva) para no
 * asociar cada oficina a mano.
 *
 *   php artisan edificio:asociar-gerencias            # aplica
 *   php artisan edificio:asociar-gerencias --simular  # solo muestra qué haría
 */
class AsociarGerenciasAPisos extends Command
{
    protected $signature = 'edificio:asociar-gerencias {--simular : Muestra qué haría, sin escribir nada}';

    protected $description = 'Asocia cada piso a su gerencia mirando el personal ya cargado';

    public function handle(AsociadorDeGerencias $asociador): int
    {
        $plan = $asociador->plan();

        if ($plan === []) {
            $this->components->warn('No hay trabajadores con piso y gerencia cargados: nada que asociar.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('   <fg=gray>Piso → Gerencia (según el personal cargado)</>');

        foreach ($plan as $piso => $info) {
            $aviso = $info['conflicto'] ? ' <fg=yellow>(conviven varias, se toma la mayoría)</>' : '';
            $this->line(sprintf('   <fg=cyan>%-8s</> %s%s', $piso, $info['gerencia'], $aviso));

            if ($info['conflicto']) {
                foreach ($info['detalle'] as $gerencia => $n) {
                    $this->line("       <fg=gray>· {$gerencia}: {$n}</>");
                }
            }
        }

        $simular = (bool) $this->option('simular');
        $r = $asociador->aplicar($simular);

        $this->newLine();

        if ($simular) {
            $this->components->info(sprintf(
                'Simulación: se crearían %d y se actualizarían %d (se respetan %d con gerencia a mano). Corre sin --simular para aplicar.',
                $r['creadas'], $r['actualizadas'], $r['saltadas'],
            ));
        } else {
            $this->components->info(sprintf(
                'Listo: %d pisos creados, %d actualizados, %d respetados.',
                $r['creadas'], $r['actualizadas'], $r['saltadas'],
            ));
        }

        return self::SUCCESS;
    }
}
