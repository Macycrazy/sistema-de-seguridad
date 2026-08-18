<?php

namespace Tests\Feature\Visitas;

use App\Models\VisitaEsperada;
use App\Services\Visitas\VisitasEsperadas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisitasEsperadasTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function agenda_normalizando_nombre_y_cedula(): void
    {
        $visita = app(VisitasEsperadas::class)->agendar(
            nombre: 'juan pérez',
            cedula: '12.345.678',
            aQuienVisita: 'Gerente de Tecnología',
            fechaEsperada: '2026-08-20',
        );

        $this->assertSame('JUAN PÉREZ', $visita->nombre);
        $this->assertSame('12345678', $visita->cedula);
        $this->assertSame(VisitaEsperada::ESPERADA, $visita->estado);
        $this->assertSame('2026-08-20', $visita->fecha_esperada->toDateString());
    }

    #[Test]
    public function sin_nombre_no_se_agenda(): void
    {
        $this->expectException(ValidationException::class);
        app(VisitasEsperadas::class)->agendar(nombre: '   ');
    }

    #[Test]
    public function sin_fecha_se_agenda_para_hoy(): void
    {
        $visita = app(VisitasEsperadas::class)->agendar(nombre: 'ANA');

        $this->assertSame(CarbonImmutable::today()->toDateString(), $visita->fecha_esperada->toDateString());
    }

    #[Test]
    public function una_fecha_ilegible_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        app(VisitasEsperadas::class)->agendar(nombre: 'ANA', fechaEsperada: 'no-es-fecha');
    }

    #[Test]
    public function marcar_llegada_y_cancelar_cambian_el_estado(): void
    {
        $servicio = app(VisitasEsperadas::class);
        $llega = $servicio->agendar(nombre: 'ANA');
        $cancela = $servicio->agendar(nombre: 'LUIS');

        $servicio->marcarLlegada($llega);
        $servicio->cancelar($cancela);

        $this->assertSame(VisitaEsperada::LLEGO, $llega->fresh()->estado);
        $this->assertSame(VisitaEsperada::CANCELADA, $cancela->fresh()->estado);
    }

    #[Test]
    public function del_dia_solo_trae_las_de_ese_dia(): void
    {
        $servicio = app(VisitasEsperadas::class);
        $servicio->agendar(nombre: 'HOY', fechaEsperada: '2026-08-20');
        $servicio->agendar(nombre: 'OTRO DÍA', fechaEsperada: '2026-08-21');

        $delDia = $servicio->delDia(CarbonImmutable::parse('2026-08-20'));

        $this->assertCount(1, $delDia);
        $this->assertSame('HOY', $delDia->first()->nombre);
    }

    #[Test]
    public function esperada_hoy_es_el_puente_para_la_puerta(): void
    {
        $servicio = app(VisitasEsperadas::class);
        $servicio->agendar(nombre: 'ESPERADO', cedula: '12345678', fechaEsperada: CarbonImmutable::today()->toDateString());

        $this->assertNotNull($servicio->esperadaHoy('12.345.678'));
        $this->assertNull($servicio->esperadaHoy('99999999'));   // no agendado
    }

    #[Test]
    public function una_esperada_con_dia_pasado_esta_vencida(): void
    {
        $visita = app(VisitasEsperadas::class)->agendar(nombre: 'ANA', fechaEsperada: CarbonImmutable::yesterday()->toDateString());

        $this->assertTrue($visita->esVencida());
    }

    #[Test]
    public function una_que_ya_llego_no_esta_vencida_aunque_sea_de_ayer(): void
    {
        $servicio = app(VisitasEsperadas::class);
        $visita = $servicio->agendar(nombre: 'ANA', fechaEsperada: CarbonImmutable::yesterday()->toDateString());
        $servicio->marcarLlegada($visita);

        $this->assertFalse($visita->fresh()->esVencida());
    }
}
