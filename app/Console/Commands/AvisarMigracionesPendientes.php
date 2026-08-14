<?php

namespace App\Console\Commands;

use App\Services\MigracionesPendientes;
use Illuminate\Console\Command;

/**
 * Avisa por consola si hay migraciones sin correr.
 *
 * Lo llama el hook de git de .githooks/post-merge, para que quien se baje los cambios de otra
 * parte se entere en el momento y no cuando la pantalla reviente con un error de columna.
 *
 * SIEMPRE termina en 0, aunque haya pendientes: un hook de git que devuelve error interrumpe
 * la operación, y aquí solo queremos avisar, no impedir el «git pull».
 */
class AvisarMigracionesPendientes extends Command
{
    protected $signature = 'migraciones:pendientes';

    protected $description = 'Avisa si hay migraciones sin correr en esta base de datos';

    public function handle(MigracionesPendientes $pendientes): int
    {
        $lista = $pendientes->listar();

        if ($lista === []) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->warn(sprintf(
            'Hay %d %s sin correr en tu base de datos.',
            count($lista),
            count($lista) === 1 ? 'migración' : 'migraciones',
        ));

        foreach ($lista as $migracion) {
            $this->line("   <fg=gray>{$migracion}</>");
        }

        $this->newLine();
        $this->line('   Alguien cambió las tablas. Corre esto antes de seguir:');
        $this->line('   <fg=cyan>php artisan migrate</>');
        $this->newLine();
        $this->line('   <fg=gray>Qué cambia cada una y por qué: docs/esquema.md</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
