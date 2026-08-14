<?php

namespace App\Services;

use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Qué migraciones están en el proyecto pero todavía no se han corrido en esta base.
 *
 * Existe por un problema real del equipo: las tres partes tocan las mismas dos o tres tablas, y
 * quien se baja los cambios de otro y se olvida de «php artisan migrate» se encuentra con errores
 * de columna que no dicen nada de lo que pasa de verdad. La pantalla lo avisa antes, con su nombre.
 *
 * Solo se usa en desarrollo. En el servidor las migraciones las corre quien despliega.
 */
class MigracionesPendientes
{
    public function __construct(private Migrator $migrator) {}

    /**
     * Los nombres de las migraciones sin correr, de la más vieja a la más nueva.
     *
     * Si la base no responde —o ni siquiera existe la tabla de migraciones, que es justo lo que
     * pasa al montar el proyecto por primera vez— devuelve una lista vacía en vez de reventar.
     * Este aviso es una ayuda; que falle no puede tumbar la pantalla.
     */
    public function listar(): array
    {
        try {
            if (! $this->migrator->repositoryExists()) {
                return [];
            }

            $corridas = $this->migrator->getRepository()->getRan();

            $enElProyecto = array_keys($this->migrator->getMigrationFiles(
                $this->migrator->paths() ?: [database_path('migrations')],
            ));

            return array_values(array_diff($enElProyecto, $corridas));
        } catch (Throwable) {
            return [];
        }
    }

    public function hay(): bool
    {
        return $this->listar() !== [];
    }
}
