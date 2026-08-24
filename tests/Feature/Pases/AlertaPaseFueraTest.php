<?php

namespace Tests\Feature\Pases;

use App\Models\Movimiento;
use App\Models\Parametro;
use App\Models\Pase;
use App\Models\Persona;
use App\Services\Alertas\Alerta;
use App\Services\Alertas\Alertas;
use App\Services\Pases\Pases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El aviso de los pases que están en la calle.
 *
 * Se avisa desde que se entregan —saber cuáles están fuera es justo el punto de contarlos— y el
 * plazo solo decide cuándo deja de ser una visita larga y pasa a ser un pase que no vuelve.
 */
class AlertaPaseFueraTest extends TestCase
{
    use RefreshDatabase;

    /** Un visitante DENTRO con su pase: es el caso normal, y el pase se da al entrar. */
    private function entregar(string $hace = '5 minutes'): void
    {
        $persona = Persona::create([
            'cedula' => '11111111', 'tipo' => Persona::INVITADO,
            'nombre' => 'ANA PÉREZ', 'motivo' => 'REUNIÓN', 'activo' => true,
        ]);

        Movimiento::create([
            'persona_id' => $persona->id,
            'tipo' => Movimiento::ENTRADA,
            'ocurrio_en' => now()->sub($hace),
        ]);

        $entrega = app(Pases::class)->entregar(Pase::create(['codigo' => 'V-01']), $persona);
        $entrega->update(['entregado_en' => now()->sub($hace)]);
    }

    /** @return Collection<int, Alerta> */
    private function dePases()
    {
        return app(Alertas::class)->activas()->where('tipo', Alerta::PASE_FUERA)->values();
    }

    #[Test]
    public function avisa_desde_que_se_entrega(): void
    {
        $this->entregar();

        $alertas = $this->dePases();

        $this->assertCount(1, $alertas);
        $this->assertFalse($alertas[0]->esUrgente(), 'Recién entregado es un aviso, no una urgencia.');
        $this->assertStringContainsString('V-01', $alertas[0]->titulo);
        $this->assertStringContainsString('ANA PÉREZ', $alertas[0]->detalle);
    }

    #[Test]
    public function pasado_el_plazo_pasa_a_urgente(): void
    {
        // Por omisión son 4 horas: una visita larga no es un pase perdido.
        $this->entregar('6 hours');

        $this->assertTrue($this->dePases()[0]->esUrgente());
    }

    #[Test]
    public function si_la_persona_ya_salio_del_edificio_el_pase_es_urgente_desde_el_primer_minuto(): void
    {
        // Que el pase esté fuera con su visitante dentro es lo normal. Que esté fuera cuando esa
        // persona ya se fue significa que el pase se fue con ella, y eso no espera a ningún plazo.
        $persona = Persona::create([
            'cedula' => '11111111', 'tipo' => Persona::INVITADO,
            'nombre' => 'ANA PÉREZ', 'motivo' => 'REUNIÓN', 'activo' => true,
        ]);

        Movimiento::create(['persona_id' => $persona->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => now()->subHours(2)]);
        app(Pases::class)->entregar(Pase::create(['codigo' => 'V-01']), $persona);
        Movimiento::create(['persona_id' => $persona->id, 'tipo' => Movimiento::SALIDA, 'ocurrio_en' => now()->subMinutes(5)]);

        $alerta = $this->dePases()[0];

        $this->assertTrue($alerta->esUrgente());
        $this->assertStringContainsString('se fue sin devolverse', $alerta->titulo);
        $this->assertStringContainsString('no volvió', $alerta->detalle);
    }

    #[Test]
    public function mientras_el_visitante_sigue_dentro_el_pase_es_solo_un_aviso(): void
    {
        $persona = Persona::create([
            'cedula' => '22222222', 'tipo' => Persona::INVITADO,
            'nombre' => 'LUIS GÓMEZ', 'motivo' => 'REUNIÓN', 'activo' => true,
        ]);

        Movimiento::create(['persona_id' => $persona->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => now()->subHour()]);
        app(Pases::class)->entregar(Pase::create(['codigo' => 'V-02']), $persona);

        $this->assertFalse($this->dePases()[0]->esUrgente());
    }

    #[Test]
    public function el_devuelto_deja_de_avisar(): void
    {
        $this->entregar('6 hours');
        app(Pases::class)->devolver(app(Pases::class)->fuera()->first());

        $this->assertCount(0, $this->dePases());
    }

    #[Test]
    public function en_cero_el_aviso_queda_apagado(): void
    {
        Parametro::updateOrCreate(['clave' => 'alerta_horas_pase_fuera'], ['valor' => 0]);
        $this->entregar('3 days');

        $this->assertCount(0, $this->dePases());
    }
}
