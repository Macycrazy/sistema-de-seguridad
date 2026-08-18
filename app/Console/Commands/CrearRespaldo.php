<?php

namespace App\Console\Commands;

use App\Services\Auditoria\Auditoria;
use App\Services\Respaldos\Respaldos;
use Illuminate\Console\Command;
use Throwable;

/**
 * Crea un respaldo de la base desde la consola.
 *
 * Para correrlo a mano o desde un cron que el administrador decida poner. La pantalla de respaldos
 * hace lo mismo con un botón; esto es para el que prefiere la terminal o quiere automatizarlo.
 */
class CrearRespaldo extends Command
{
    protected $signature = 'respaldo:crear';

    protected $description = 'Crea una copia de seguridad de la base de datos';

    public function handle(Respaldos $respaldos, Auditoria $auditoria): int
    {
        $this->info('Creando respaldo…');

        try {
            $resultado = $respaldos->crear();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $auditoria->respaldo('creó '.$resultado['archivo']);

        $this->info('Listo: '.$resultado['archivo'].' ('.number_format($resultado['bytes']).' bytes).');

        return self::SUCCESS;
    }
}
