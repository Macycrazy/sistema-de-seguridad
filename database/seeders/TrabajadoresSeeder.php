<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;

/**
 * Trabajadores de prueba para poder usar la pantalla de marcar en desarrollo.
 *
 * TODOS LOS DATOS SON INVENTADOS. La lista real de personal viene del sistema de carnets, cuyo
 * enlace no forma parte de estas tres partes; y la base real no se copia a la máquina de nadie.
 *
 * Las cédulas son fáciles de teclear a propósito, para probar rápido.
 */
class TrabajadoresSeeder extends Seeder
{
    public function run(): void
    {
        $trabajadores = [
            ['cedula' => '11111111', 'nombre' => 'Ana Rodríguez Peña', 'dependencia' => 'Recursos Humanos'],
            ['cedula' => '22222222', 'nombre' => 'Luis Hernández Mora', 'dependencia' => 'Tecnología de la Información'],
            ['cedula' => '33333333', 'nombre' => 'Carmen Díaz Silva', 'dependencia' => 'Administración'],
            ['cedula' => '44444444', 'nombre' => 'José Martínez Rojas', 'dependencia' => 'Mantenimiento'],
            ['cedula' => '55555555', 'nombre' => 'María Fernández Ruiz', 'dependencia' => 'Contabilidad'],
            ['cedula' => '66666666', 'nombre' => 'Pedro Gómez Alvarado', 'dependencia' => 'Seguridad'],
            ['cedula' => '77777777', 'nombre' => 'Rosa Blanco Ceballos', 'dependencia' => 'Almacén'],
            ['cedula' => '88888888', 'nombre' => 'Miguel Suárez Lugo', 'dependencia' => 'Tecnología de la Información'],
            ['cedula' => '12345678', 'nombre' => 'Daniela Paredes Ortiz', 'dependencia' => 'Dirección'],
            ['cedula' => '87654321', 'nombre' => 'Rafael Montero Vega', 'dependencia' => 'Planificación'],
        ];

        foreach ($trabajadores as $trabajador) {
            Persona::updateOrCreate(
                ['cedula' => $trabajador['cedula']],
                [
                    'tipo' => Persona::TRABAJADOR,
                    'nombre' => $trabajador['nombre'],
                    'dependencia' => $trabajador['dependencia'],
                    'activo' => true,
                ],
            );
        }

        // Uno desactivado, para comprobar que el sistema no lo deja marcar.
        Persona::updateOrCreate(
            ['cedula' => '99999999'],
            [
                'tipo' => Persona::TRABAJADOR,
                'nombre' => 'Elena Castro Ávila',
                'dependencia' => 'Recursos Humanos',
                'activo' => false,
            ],
        );
    }
}
