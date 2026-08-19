<?php

namespace Tests\Feature\Alertas;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Alertas\Alerta;
use App\Services\Alertas\Alertas;
use App\Services\Alertas\UmbralesDeAlerta;
use App\Services\DatosVehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertasTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $cedula, string $tipo = Persona::TRABAJADOR): Persona
    {
        return Persona::create(['cedula' => $cedula, 'tipo' => $tipo, 'nombre' => 'PERSONA '.$cedula, 'activo' => true]);
    }

    private function marca(Persona $persona, string $tipo, CarbonImmutable $cuando): void
    {
        Movimiento::create(['persona_id' => $persona->id, 'tipo' => $tipo, 'ocurrio_en' => $cuando]);
    }

    #[Test]
    public function sin_nadie_de_mas_no_hay_alertas(): void
    {
        $ana = $this->persona('1');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subHours(2));   // dentro, pero poco

        $this->assertTrue(app(Alertas::class)->activas()->isEmpty());
    }

    #[Test]
    public function quien_lleva_de_mas_dentro_dispara_permanencia(): void
    {
        $ana = $this->persona('1');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subHours(13));   // umbral por omisión: 12 h

        $alertas = app(Alertas::class)->activas();

        $this->assertCount(1, $alertas);
        $this->assertSame(Alerta::PERMANENCIA, $alertas->first()->tipo);
        $this->assertSame($ana->id, (int) $alertas->first()->personaId);
    }

    #[Test]
    public function quien_ya_salio_no_dispara_permanencia(): void
    {
        $ana = $this->persona('1');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subHours(13));
        $this->marca($ana, Movimiento::SALIDA, CarbonImmutable::now()->subHours(1));   // su último movimiento es salida

        $this->assertTrue(app(Alertas::class)->activas()->isEmpty());
    }

    #[Test]
    public function al_doble_del_umbral_la_permanencia_es_urgente(): void
    {
        $ana = $this->persona('1');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subHours(25));   // >= 2 × 12

        $this->assertTrue(app(Alertas::class)->activas()->first()->esUrgente());
    }

    #[Test]
    public function el_aforo_solo_avisa_si_esta_configurado_y_se_supera(): void
    {
        app(UmbralesDeAlerta::class)->guardar('alerta_aforo', 1);

        $ana = $this->persona('1');
        $luis = $this->persona('2');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subMinutes(30));
        $this->marca($luis, Movimiento::ENTRADA, CarbonImmutable::now()->subMinutes(20));

        $alertas = app(Alertas::class)->activas();
        $aforo = $alertas->firstWhere('tipo', Alerta::AFORO);

        $this->assertNotNull($aforo);
        $this->assertTrue($aforo->esUrgente());
    }

    #[Test]
    public function el_aforo_en_cero_no_avisa_nunca(): void
    {
        // Por omisión el aforo es 0 (desactivado).
        $ana = $this->persona('1');
        $luis = $this->persona('2');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subMinutes(30));
        $this->marca($luis, Movimiento::ENTRADA, CarbonImmutable::now()->subMinutes(20));

        $this->assertNull(app(Alertas::class)->activas()->firstWhere('tipo', Alerta::AFORO));
    }

    #[Test]
    public function el_estacionamiento_avisa_cuando_se_pasa_del_aforo(): void
    {
        app(UmbralesDeAlerta::class)->guardar('alerta_aforo_estacionamiento', 1);

        $ana = $this->persona('1');
        $luis = $this->persona('2');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subMinutes(30));
        $ana->movimientos()->latest('id')->first()->update(['tipo_vehiculo' => DatosVehiculo::CARRO, 'placa' => 'AAA111']);
        $this->marca($luis, Movimiento::ENTRADA, CarbonImmutable::now()->subMinutes(20));
        $luis->movimientos()->latest('id')->first()->update(['tipo_vehiculo' => DatosVehiculo::MOTO, 'placa' => 'BBB222']);

        $alerta = app(Alertas::class)->activas()->firstWhere('tipo', Alerta::ESTACIONAMIENTO);

        $this->assertNotNull($alerta);
        $this->assertTrue($alerta->esUrgente());
    }

    #[Test]
    public function el_estacionamiento_en_cero_no_avisa(): void
    {
        $ana = $this->persona('1');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subMinutes(30));
        $ana->movimientos()->latest('id')->first()->update(['tipo_vehiculo' => DatosVehiculo::CARRO, 'placa' => 'AAA111']);

        $this->assertNull(app(Alertas::class)->activas()->firstWhere('tipo', Alerta::ESTACIONAMIENTO));
    }

    #[Test]
    public function avisa_por_tipo_cuando_se_llenan_los_puestos_de_carros(): void
    {
        app(UmbralesDeAlerta::class)->guardar('alerta_aforo_carro', 1);

        foreach (['1', '2'] as $ci) {
            $p = $this->persona($ci);
            $this->marca($p, Movimiento::ENTRADA, CarbonImmutable::now()->subMinutes(20));
            $p->movimientos()->latest('id')->first()->update(['tipo_vehiculo' => DatosVehiculo::CARRO, 'placa' => 'P'.$ci]);
        }

        $this->assertNotNull(app(Alertas::class)->activas()->firstWhere('titulo', 'Sin puestos de carros'));
    }

    #[Test]
    public function las_urgentes_van_primero(): void
    {
        app(UmbralesDeAlerta::class)->guardar('alerta_aforo', 1);
        $ana = $this->persona('1');
        $luis = $this->persona('2');
        $this->marca($ana, Movimiento::ENTRADA, CarbonImmutable::now()->subHours(13));   // aviso
        $this->marca($luis, Movimiento::ENTRADA, CarbonImmutable::now()->subMinutes(20));   // llena aforo

        $alertas = app(Alertas::class)->activas();

        $this->assertTrue($alertas->first()->esUrgente());   // el aforo (urgente) arriba
    }
}
