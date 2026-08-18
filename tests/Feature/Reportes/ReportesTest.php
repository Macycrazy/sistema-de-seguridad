<?php

namespace Tests\Feature\Reportes;

use App\Models\Departamento;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Reportes\Reportes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $cedula, string $tipo = Persona::TRABAJADOR): Persona
    {
        return Persona::create([
            'cedula' => $cedula,
            'tipo' => $tipo,
            'nombre' => 'PERSONA '.$cedula,
            'activo' => true,
        ]);
    }

    private function entrada(Persona $persona, string $cuando): void
    {
        Movimiento::create([
            'persona_id' => $persona->id,
            'tipo' => Movimiento::ENTRADA,
            'ocurrio_en' => $cuando,
        ]);
    }

    #[Test]
    public function el_resumen_cuenta_entradas_y_personas_distintas_no_las_salidas(): void
    {
        $ana = $this->persona('1');
        $luis = $this->persona('2');

        $this->entrada($ana, '2026-08-10 08:00');
        $this->entrada($ana, '2026-08-11 08:00');   // Ana, dos días
        $this->entrada($luis, '2026-08-10 09:00');
        // Una salida no debe sumar como entrada.
        Movimiento::create(['persona_id' => $luis->id, 'tipo' => Movimiento::SALIDA, 'ocurrio_en' => '2026-08-10 17:00']);

        $resumen = app(Reportes::class)->resumen(
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-31'),
        );

        $this->assertSame(3, $resumen['entradas']);
        $this->assertSame(2, $resumen['personas']);
        $this->assertSame(2, $resumen['dias']);   // días con actividad: 10 y 11
    }

    #[Test]
    public function el_tramo_acota_por_fecha(): void
    {
        $ana = $this->persona('1');
        $this->entrada($ana, '2026-08-05 08:00');   // dentro
        $this->entrada($ana, '2026-07-31 08:00');   // antes del tramo
        $this->entrada($ana, '2026-09-01 08:00');   // después del tramo

        $resumen = app(Reportes::class)->resumen(
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-31'),
        );

        $this->assertSame(1, $resumen['entradas']);
    }

    #[Test]
    public function la_franja_pico_es_la_hora_con_mas_entradas(): void
    {
        $ana = $this->persona('1');
        $luis = $this->persona('2');
        $this->entrada($ana, '2026-08-10 08:15');
        $this->entrada($luis, '2026-08-10 08:45');   // dos a las 8
        $this->entrada($ana, '2026-08-10 13:00');    // una a la 1

        $resumen = app(Reportes::class)->resumen(
            CarbonImmutable::parse('2026-08-10'),
            CarbonImmutable::parse('2026-08-10'),
        );
        $franja = app(Reportes::class)->porFranja(
            CarbonImmutable::parse('2026-08-10'),
            CarbonImmutable::parse('2026-08-10'),
        );

        $this->assertSame(8, $resumen['franjaPico']);
        $this->assertSame(2, $resumen['picoEntradas']);
        $this->assertSame(2, $franja[8]);
        $this->assertSame(1, $franja[13]);
        $this->assertSame(0, $franja[20]);
    }

    #[Test]
    public function por_dia_rellena_el_calendario_con_ceros(): void
    {
        $ana = $this->persona('1');
        $this->entrada($ana, '2026-08-10 08:00');

        $porDia = app(Reportes::class)->porDia(
            CarbonImmutable::parse('2026-08-09'),
            CarbonImmutable::parse('2026-08-11'),
        );

        $this->assertCount(3, $porDia);   // 9, 10, 11 — todos presentes
        $this->assertSame(0, $porDia[0]['entradas']);   // 9
        $this->assertSame(1, $porDia[1]['entradas']);   // 10
        $this->assertSame(0, $porDia[2]['entradas']);   // 11
    }

    #[Test]
    public function por_tipo_separa_trabajadores_de_invitados(): void
    {
        $trabajador = $this->persona('1', Persona::TRABAJADOR);
        $invitado = $this->persona('2', Persona::INVITADO);
        $this->entrada($trabajador, '2026-08-10 08:00');
        $this->entrada($trabajador, '2026-08-11 08:00');
        $this->entrada($invitado, '2026-08-10 10:00');

        $porTipo = app(Reportes::class)->porTipo(
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-31'),
        );

        $this->assertSame(2, $porTipo['trabajador']);
        $this->assertSame(1, $porTipo['invitado']);
    }

    #[Test]
    public function por_departamento_agrupa_por_unidad_y_cae_al_texto_si_no_hay_enlace(): void
    {
        $dep = Departamento::create(['nombre' => 'GERENCIA A', 'nivel' => 2, 'activo' => true]);
        $conUnidad = $this->persona('1');
        $conUnidad->update(['departamento_id' => $dep->id, 'dependencia' => 'GERENCIA A']);
        $soloTexto = $this->persona('2');
        $soloTexto->update(['dependencia' => 'SIN ENLACE']);

        $this->entrada($conUnidad, '2026-08-10 08:00');
        $this->entrada($conUnidad, '2026-08-11 08:00');
        $this->entrada($soloTexto, '2026-08-10 09:00');

        $porUnidad = app(Reportes::class)->porDepartamento(
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-31'),
        );

        $this->assertSame('GERENCIA A', $porUnidad[0]['unidad']);
        $this->assertSame(2, $porUnidad[0]['entradas']);
        $this->assertSame('SIN ENLACE', $porUnidad[1]['unidad']);
        $this->assertSame(1, $porUnidad[1]['entradas']);
    }

    #[Test]
    public function por_vehiculo_separa_carro_moto_y_a_pie(): void
    {
        $ana = $this->persona('1');
        Movimiento::create(['persona_id' => $ana->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => '2026-08-10 08:00', 'tipo_vehiculo' => 'carro', 'placa' => 'AAA111']);
        Movimiento::create(['persona_id' => $ana->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => '2026-08-11 08:00', 'tipo_vehiculo' => 'moto', 'placa' => 'BBB222']);
        $this->entrada($ana, '2026-08-12 08:00');   // a pie

        $porVehiculo = app(Reportes::class)->porVehiculo(
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-31'),
        );

        $this->assertSame(1, $porVehiculo['carro']);
        $this->assertSame(1, $porVehiculo['moto']);
        $this->assertSame(1, $porVehiculo['aPie']);
    }

    #[Test]
    public function mas_frecuentes_ordena_por_numero_de_entradas(): void
    {
        $ana = $this->persona('1');
        $luis = $this->persona('2');
        $this->entrada($ana, '2026-08-10 08:00');
        $this->entrada($ana, '2026-08-11 08:00');
        $this->entrada($ana, '2026-08-12 08:00');
        $this->entrada($luis, '2026-08-10 09:00');

        $ranking = app(Reportes::class)->masFrecuentes(
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-31'),
        );

        $this->assertSame($ana->id, $ranking[0]['persona']->id);
        $this->assertSame(3, $ranking[0]['visitas']);
        $this->assertSame(1, $ranking[1]['visitas']);
    }
}
