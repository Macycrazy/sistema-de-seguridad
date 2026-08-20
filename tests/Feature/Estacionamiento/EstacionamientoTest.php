<?php

namespace Tests\Feature\Estacionamiento;

use App\Models\Puesto;
use App\Models\VehiculoFijo;
use App\Services\Alertas\UmbralesDeAlerta;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El estacionamiento tras el rediseño: los vehículos son ESTADÍAS (VehiculoFijo). Cada uno se anota
 * y se saca en el propio estacionamiento; la puerta ya no maneja vehículos.
 */
class EstacionamientoTest extends TestCase
{
    use RefreshDatabase;

    private function estadia(string $placa, string $tipo, CarbonImmutable $entro, array $extra = []): VehiculoFijo
    {
        return VehiculoFijo::create(array_merge([
            'placa' => $placa,
            'tipo_vehiculo' => $tipo,
            'entro_en' => $entro,
        ], $extra));
    }

    #[Test]
    public function cuenta_los_vehiculos_dentro_por_tipo(): void
    {
        $this->estadia('AB123CD', DatosVehiculo::CARRO, CarbonImmutable::now()->subHour());
        $this->estadia('XY9', DatosVehiculo::MOTO, CarbonImmutable::now()->subHour());

        $servicio = app(Estacionamiento::class);

        $this->assertSame(2, $servicio->cuantosDentro());
        $this->assertSame(1, $servicio->porTipoDentro()['carro']);
        $this->assertSame(1, $servicio->porTipoDentro()['moto']);
    }

    #[Test]
    public function quien_ya_salio_no_cuenta(): void
    {
        $this->estadia('AB123CD', DatosVehiculo::CARRO, CarbonImmutable::now()->subHours(3), ['salio_en' => CarbonImmutable::now()->subHour()]);

        $this->assertSame(0, app(Estacionamiento::class)->cuantosDentro());
    }

    #[Test]
    public function la_lista_trae_la_placa_y_el_conductor(): void
    {
        $this->estadia('AB123CD', DatosVehiculo::CARRO, CarbonImmutable::now()->subHour(), ['marca' => 'Toyota', 'conductor_nombre' => 'ANA PÉREZ']);

        $fila = app(Estacionamiento::class)->vehiculosDentro()->first();

        $this->assertSame('AB123CD', $fila->placa);
        $this->assertSame('ANA PÉREZ', $fila->conductor);
        $this->assertSame('Carro', $fila->vehiculo->etiquetaTipo());
    }

    #[Test]
    public function el_historial_del_dia_trae_entradas_y_salidas(): void
    {
        $this->estadia('AB123CD', DatosVehiculo::CARRO, CarbonImmutable::now()->subHours(2), ['salio_en' => CarbonImmutable::now()->subMinutes(10)]);

        $historial = app(Estacionamiento::class)->delDia(CarbonImmutable::today());

        $this->assertCount(2, $historial);
        $this->assertFalse($historial->first()->esEntrada);   // más reciente: la salida
    }

    #[Test]
    public function pernoctan_los_que_entraron_antes_de_hoy_y_siguen_dentro(): void
    {
        $this->estadia('AB123CD', DatosVehiculo::CARRO, CarbonImmutable::yesterday()->setTime(20, 0));
        $this->estadia('XY9', DatosVehiculo::CARRO, CarbonImmutable::now());   // entró hoy, no pernocta

        $pernoctan = app(Estacionamiento::class)->pernoctan();

        $this->assertCount(1, $pernoctan);
        $this->assertSame('AB123CD', $pernoctan->first()->placa);
    }

    #[Test]
    public function quien_pernocto_pero_ya_salio_no_cuenta(): void
    {
        $this->estadia('AB123CD', DatosVehiculo::CARRO, CarbonImmutable::yesterday()->setTime(20, 0), ['salio_en' => CarbonImmutable::now()]);

        $this->assertTrue(app(Estacionamiento::class)->pernoctan()->isEmpty());
    }

    #[Test]
    public function el_reporte_por_noche_trae_a_quien_estaba_esa_medianoche(): void
    {
        $laNoche = CarbonImmutable::yesterday()->subDay();

        $this->estadia('AB123CD', DatosVehiculo::CARRO, $laNoche->setTime(20, 0));
        $this->estadia('XY9', DatosVehiculo::CARRO, $laNoche->setTime(9, 0), ['salio_en' => $laNoche->setTime(17, 0)]);

        $reporte = app(Estacionamiento::class)->pernoctaronLaNoche($laNoche);

        $this->assertCount(1, $reporte);
        $this->assertSame('AB123CD', $reporte->first()->placa);
    }

    #[Test]
    public function el_tiempo_dentro_se_dice_corto(): void
    {
        $hace = CarbonImmutable::now()->subHours(2)->subMinutes(15)->toDateTimeString();

        $this->assertStringContainsString('2 h', app(Estacionamiento::class)->tiempoDentro($hace));
    }

    #[Test]
    public function los_aforos_por_tipo_se_leen(): void
    {
        $umbrales = app(UmbralesDeAlerta::class);
        $umbrales->guardar('alerta_aforo_carro', 40);
        $umbrales->guardar('alerta_aforo_moto', 10);

        $aforos = app(Estacionamiento::class)->aforos();

        $this->assertSame(40, $aforos['carro']);
        $this->assertSame(10, $aforos['moto']);
    }

    #[Test]
    public function un_puesto_ocupado_por_una_estadia_no_sale_como_libre(): void
    {
        $a1 = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        Puesto::create(['codigo' => 'A-2', 'orden' => 2]);
        $this->estadia('AB123CD', DatosVehiculo::CARRO, CarbonImmutable::now()->subHour(), ['puesto_id' => $a1->id]);

        $this->assertSame(['A-2'], app(Estacionamiento::class)->puestosLibres()->pluck('codigo')->all());
    }
}
