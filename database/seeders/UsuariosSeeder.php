<?php

namespace Database\Seeders;

use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Database\Seeder;

/**
 * Un usuario por rol, para poder usar el sistema en desarrollo.
 *
 * TODOS LOS DATOS SON INVENTADOS, y la clave está aquí a la vista a propósito: esto no se corre
 * en el servidor. El primer administrador de verdad no sale de un seeder —hay que decidir de
 * dónde sale, ver docs/parte-3.md—.
 *
 * Aquí no hay correo de nadie: a un usuario lo identifican su nombre, su cédula y el nombre de
 * usuario con el que entra.
 */
class UsuariosSeeder extends Seeder
{
    /** La misma para los cuatro: es de mentira y hay que poder teclearla mil veces. */
    public const CLAVE = 'ciip1234';

    public function run(): void
    {
        /*
         * Fuera de local no corre, y esto no es exageración: sembraría cuatro usuarios con una
         * clave que está escrita a la vista en este mismo archivo, que está en el repositorio.
         * Un «php artisan db:seed» tecleado por costumbre en el servidor dejaría cuatro puertas
         * abiertas, una de ellas de administrador.
         *
         * En el servidor, el primer usuario se crea con «php artisan usuario:crear».
         */
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn(
                'UsuariosSeeder no corre fuera de local: son usuarios de mentira con una clave '
                .'pública. Usa «php artisan usuario:crear».'
            );

            return;
        }

        $usuarios = [
            ['usuario' => 'vigilante', 'nombre' => 'Ana Rodríguez Peña', 'cedula' => '10000001', 'rol' => Rol::VIGILANTE, 'activo' => true],
            ['usuario' => 'supervisor', 'nombre' => 'Luis Hernández Mora', 'cedula' => '10000002', 'rol' => Rol::SUPERVISOR, 'activo' => true],
            ['usuario' => 'admin', 'nombre' => 'Carmen Díaz Silva', 'cedula' => '10000003', 'rol' => Rol::ADMINISTRADOR, 'activo' => true],

            // Desactivado a propósito, igual que el trabajador de cédula 99999999: para
            // comprobar a mano que el sistema no lo deja entrar.
            ['usuario' => 'exvigilante', 'nombre' => 'José Martínez Rojas', 'cedula' => '10000004', 'rol' => Rol::VIGILANTE, 'activo' => false],
        ];

        foreach ($usuarios as $usuario) {
            User::updateOrCreate(
                ['usuario' => $usuario['usuario']],
                [
                    'nombre' => $usuario['nombre'],
                    'cedula' => $usuario['cedula'],
                    'rol' => $usuario['rol'],
                    'activo' => $usuario['activo'],
                    // El cast «hashed» del modelo la guarda hasheada: aquí va en claro y en la
                    // base nunca.
                    'password' => self::CLAVE,
                ],
            );
        }
    }
}
