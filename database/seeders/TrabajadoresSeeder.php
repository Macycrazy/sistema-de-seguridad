<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Services\Vehiculo;
use Illuminate\Database\Seeder;

/**
 * Trabajadores de prueba para poder usar la pantalla de marcar en desarrollo.
 *
 * LOS NOMBRES Y LAS CÉDULAS SON INVENTADOS. La lista real de personal viene del sistema de
 * carnets, cuyo enlace no forma parte de estas tres partes; y la base real no se copia a la
 * máquina de nadie.
 *
 * Las GERENCIAS sí son las del CIIP, porque son las que hay que ver en pantalla al probar: de
 * nada sirve comprobar que sale «Administración» si ese departamento no existe aquí.
 *
 * Las cédulas son fáciles de teclear a propósito, para probar rápido.
 */
class TrabajadoresSeeder extends Seeder
{
    /**
     * Las gerencias del CIIP. Se declaran una vez y se reparten abajo, para que no se cuele un
     * nombre mal escrito en una fila suelta.
     */
    public const TECNOLOGIA = 'Tecnología';

    public const PLANIFICACION = 'Planificación y Presupuesto';

    public const GESTION_HUMANA = 'Gestión Humana';

    public const JURIDICA = 'Consultoría Jurídica';

    public function run(): void
    {
        // Tres llegan en vehículo y el resto caminando, que es la proporción con la que hay que
        // probar: lo normal es entrar a pie, y el vehículo tiene que verse como la excepción.
        // Dos carros y una moto, para que se vean los dos tipos en pantalla.
        $trabajadores = [
            ['cedula' => '11111111', 'nombre' => 'Ana Rodríguez Peña', 'gerencia' => self::GESTION_HUMANA],
            ['cedula' => '22222222', 'nombre' => 'Luis Hernández Mora', 'gerencia' => self::TECNOLOGIA, 'vehiculo' => [Vehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', 'AB123CD']],
            ['cedula' => '33333333', 'nombre' => 'Carmen Díaz Silva', 'gerencia' => self::PLANIFICACION],
            ['cedula' => '44444444', 'nombre' => 'José Martínez Rojas', 'gerencia' => self::JURIDICA, 'vehiculo' => [Vehiculo::MOTO, 'Bera', 'BR-150', 'Negro', 'AC456DF']],
            ['cedula' => '55555555', 'nombre' => 'María Fernández Ruiz', 'gerencia' => self::PLANIFICACION],
            ['cedula' => '66666666', 'nombre' => 'Pedro Gómez Alvarado', 'gerencia' => self::TECNOLOGIA],
            ['cedula' => '77777777', 'nombre' => 'Rosa Blanco Ceballos', 'gerencia' => self::GESTION_HUMANA],
            ['cedula' => '88888888', 'nombre' => 'Miguel Suárez Lugo', 'gerencia' => self::TECNOLOGIA],
            ['cedula' => '12345678', 'nombre' => 'Daniela Paredes Ortiz', 'gerencia' => self::JURIDICA, 'vehiculo' => [Vehiculo::CARRO, 'Chevrolet', 'Aveo', 'Azul', 'AD789GH']],
            ['cedula' => '87654321', 'nombre' => 'Rafael Montero Vega', 'gerencia' => self::PLANIFICACION],
        ];

        foreach ($trabajadores as $trabajador) {
            $vehiculo = Vehiculo::desde(...($trabajador['vehiculo'] ?? []));

            Persona::updateOrCreate(
                ['cedula' => $trabajador['cedula']],
                [
                    'tipo' => Persona::TRABAJADOR,
                    'nombre' => $trabajador['nombre'],
                    // La columna se sigue llamando «dependencia» en la base: renombrarla es un
                    // cambio de esquema que hay que hablar con las otras dos partes. En pantalla
                    // se lee «Gerencia», que es como se dice aquí.
                    'dependencia' => $trabajador['gerencia'],
                    'activo' => true,
                    ...$vehiculo->paraGuardar(),
                ],
            );
        }

        // Uno desactivado, para comprobar que el sistema no lo deja marcar.
        Persona::updateOrCreate(
            ['cedula' => '99999999'],
            [
                'tipo' => Persona::TRABAJADOR,
                'nombre' => 'Elena Castro Ávila',
                'dependencia' => self::GESTION_HUMANA,
                'activo' => false,
            ],
        );
    }
}
