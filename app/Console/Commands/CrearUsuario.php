<?php

namespace App\Console\Commands;

use App\Services\GestionDeUsuarios;
use App\Usuarios\Rol;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Da de alta a un usuario desde la consola.
 *
 * Existe por el problema del huevo y la gallina: la pantalla de usuarios solo la abre un
 * administrador, y en un servidor recién montado no hay ninguno. Un seeder no sirve —los seeders
 * son de desarrollo y traen datos inventados—, así que el primer administrador se crea con esto.
 *
 * Después del primero, lo normal es usar la pantalla.
 */
class CrearUsuario extends Command
{
    protected $signature = 'usuario:crear
                            {usuario? : El nombre con el que entra}
                            {--nombre= : Nombre y apellido}
                            {--cedula= : Solo dígitos. Opcional}
                            {--clave= : La clave con la que entra la primera vez}
                            {--rol=administrador : vigilante, supervisor o administrador}';

    protected $description = 'Da de alta a un usuario del sistema';

    public function handle(GestionDeUsuarios $gestion): int
    {
        $usuario = (string) ($this->argument('usuario') ?: $this->ask('Nombre de usuario'));
        $nombre = (string) ($this->option('nombre') ?: $this->ask('Nombre y apellido'));
        $cedula = $this->option('cedula');
        $rol = Rol::tryFrom((string) $this->option('rol'));

        if ($rol === null) {
            $this->error('El rol tiene que ser vigilante, supervisor o administrador.');

            return self::FAILURE;
        }

        // La clave la pone quien corre el comando, igual que en la pantalla: el sistema no
        // inventa ninguna. Con «secret» no queda escrita en la terminal ni en el historial.
        $clave = (string) ($this->option('clave') ?: $this->secret('Clave con la que entrará la primera vez'));

        try {
            $creado = $gestion->crear($usuario, $nombre, $cedula, $rol, $clave);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->error($mensaje);
                }
            }

            return self::FAILURE;
        }

        $this->info("Usuario «{$creado->usuario}» creado como {$rol->etiqueta()}.");
        $this->comment('Ya puede entrar con esa clave. Si quiere una suya, la cambia desde el sistema.');

        return self::SUCCESS;
    }
}
