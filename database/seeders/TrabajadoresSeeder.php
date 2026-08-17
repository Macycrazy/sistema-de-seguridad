<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Services\DatosVehiculo;
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
        //
        // Luis tiene DOS —carro y moto—, que es el caso que hay que poder probar: en la puerta
        // se le marca cuál de los dos trae ese día. Los otros dos tienen uno solo, y entre los
        // tres se ven las dos clases.
        // El piso va con el código del edificio: «2-1», «2-2» y así. La gente de una misma
        // gerencia comparte piso, que es como está repartido el edificio de verdad.
        // El ente reparte al personal entre los tres organismos del edificio. La mayoría es de
        // CIIP; se siembran un par de Marca País y VENAPP para que el filtro del registro se vea
        // funcionando con las tres opciones.
        $trabajadores = [
            ['cedula' => '11111111', 'nombre' => 'Ana Rodríguez Peña', 'ente' => Persona::ENTE_CIIP, 'gerencia' => self::GESTION_HUMANA, 'piso' => '3-1'],
            ['cedula' => '22222222', 'nombre' => 'Luis Hernández Mora', 'ente' => Persona::ENTE_CIIP, 'gerencia' => self::TECNOLOGIA, 'piso' => '2-1', 'vehiculos' => [
                [DatosVehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', 'AB123CD'],
                [DatosVehiculo::MOTO, 'Empire', 'Horse', 'Rojo', 'AE321JK'],
            ]],
            ['cedula' => '33333333', 'nombre' => 'Carmen Díaz Silva', 'ente' => Persona::ENTE_CIIP, 'gerencia' => self::PLANIFICACION, 'piso' => '2-2'],
            ['cedula' => '44444444', 'nombre' => 'José Martínez Rojas', 'ente' => Persona::ENTE_CIIP, 'gerencia' => self::JURIDICA, 'piso' => '4-1', 'vehiculos' => [
                [DatosVehiculo::MOTO, 'Bera', 'BR-150', 'Negro', 'AC456DF'],
            ]],
            ['cedula' => '55555555', 'nombre' => 'María Fernández Ruiz', 'ente' => Persona::ENTE_CIIP, 'gerencia' => self::PLANIFICACION, 'piso' => '2-2'],
            ['cedula' => '66666666', 'nombre' => 'Pedro Gómez Alvarado', 'ente' => Persona::ENTE_CIIP, 'gerencia' => self::TECNOLOGIA, 'piso' => '2-1'],
            ['cedula' => '77777777', 'nombre' => 'Rosa Blanco Ceballos', 'ente' => Persona::ENTE_MARCA_PAIS, 'gerencia' => self::GESTION_HUMANA, 'piso' => '3-1'],
            ['cedula' => '88888888', 'nombre' => 'Miguel Suárez Lugo', 'ente' => Persona::ENTE_MARCA_PAIS, 'gerencia' => self::TECNOLOGIA, 'piso' => '2-1'],
            ['cedula' => '12345678', 'nombre' => 'Daniela Paredes Ortiz', 'ente' => Persona::ENTE_VENAPP, 'gerencia' => self::JURIDICA, 'piso' => '4-1', 'vehiculos' => [
                [DatosVehiculo::CARRO, 'Chevrolet', 'Aveo', 'Azul', 'AD789GH'],
            ]],
            ['cedula' => '87654321', 'nombre' => 'Rafael Montero Vega', 'ente' => Persona::ENTE_VENAPP, 'gerencia' => self::PLANIFICACION, 'piso' => '2-2'],
        ];

        foreach ($trabajadores as $trabajador) {
            $persona = Persona::updateOrCreate(
                ['cedula' => $trabajador['cedula']],
                [
                    'tipo' => Persona::TRABAJADOR,
                    'ente' => $trabajador['ente'],
                    'nombre' => $trabajador['nombre'],
                    // La columna se sigue llamando «dependencia» en la base: renombrarla es un
                    // cambio de esquema que hay que hablar con las otras dos partes. En pantalla
                    // se lee «Gerencia», que es como se dice aquí.
                    'dependencia' => $trabajador['gerencia'],
                    'piso' => $trabajador['piso'],
                    'activo' => true,
                ],
            );

            // updateOrCreate por la placa, para que sembrar dos veces no duplique nada.
            foreach ($trabajador['vehiculos'] ?? [] as $datos) {
                $vehiculo = DatosVehiculo::desde(...$datos);

                $persona->vehiculos()->updateOrCreate(
                    ['placa' => $vehiculo->placa],
                    $vehiculo->paraGuardarEnLaTabla(),
                );
            }
        }

        // Uno desactivado, para comprobar que el sistema no lo deja marcar.
        Persona::updateOrCreate(
            ['cedula' => '99999999'],
            [
                'tipo' => Persona::TRABAJADOR,
                'nombre' => 'Elena Castro Ávila',
                'dependencia' => self::GESTION_HUMANA,
                'piso' => '3-1',
                'activo' => false,
            ],
        );
    }
}
