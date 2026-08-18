<?php

namespace Tests\Feature\Estacionamiento;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EstacionamientoTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $cedula): Persona
    {
        return Persona::create(['cedula' => $cedula, 'tipo' => Persona::TRABAJADOR, 'nombre' => 'PERSONA '.$cedula, 'activo' => true]);
    }

    private function marca(Persona $p, string $tipo, CarbonImmutable $cuando, array $vehiculo = []): void
    {
        Movimiento::create(array_merge([
            'persona_id' => $p->id,
            'tipo' => $tipo,
            'ocurrio_en' => $cuando,
        ], $vehiculo));
    }

    #[Test]
    public function cuenta_los_vehiculos_de_quienes_estan_dentro(): void
    {
        $carro = $this->persona('1');
        $this->marca($carro, Movimiento::ENTRADA, CarbonImmutable::now()->subHour(), ['tipo_vehiculo' => DatosVehiculo::CARRO, 'placa' => 'AB123CD']);

        $moto = $this->persona('2');
        $this->marca($moto, Movimiento::ENTRADA, CarbonImmutable::now()->subHour(), ['tipo_vehiculo' => DatosVehiculo::MOTO, 'placa' => 'XY9']);

        $servicio = app(Estacionamiento::class);

        $this->assertSame(2, $servicio->cuantosDentro());
        $this->assertSame(1, $servicio->porTipoDentro()['carro']);
        $this->assertSame(1, $servicio->porTipoDentro()['moto']);
    }

    #[Test]
    public function quien_entro_a_pie_no_cuenta(): void
    {
        $aPie = $this->persona('1');
        $this->marca($aPie, Movimiento::ENTRADA, CarbonImmutable::now()->subHour());   // sin vehículo

        $this->assertSame(0, app(Estacionamiento::class)->cuantosDentro());
    }

    #[Test]
    public function quien_ya_salio_saca_su_vehiculo(): void
    {
        $ana = $this->persona('1');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subHours(3), ['tipo_vehiculo' => DatosVehiculo::CARRO, 'placa' => 'AB123CD']);
        $this->marca($ana, Movimiento::SALIDA, CarbonImmutable::now()->subHour());   // su último movimiento es salida

        $this->assertSame(0, app(Estacionamiento::class)->cuantosDentro());
    }

    #[Test]
    public function la_lista_trae_la_placa_y_el_dueno(): void
    {
        $ana = $this->persona('12345678');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subHour(), ['tipo_vehiculo' => DatosVehiculo::CARRO, 'marca' => 'Toyota', 'placa' => 'AB123CD']);

        $fila = app(Estacionamiento::class)->vehiculosDentro()->first();

        $this->assertSame('AB123CD', $fila->placa);
        $this->assertSame('PERSONA 12345678', $fila->nombre);
        $this->assertSame('Carro', $fila->vehiculo->etiquetaTipo());
    }
}
