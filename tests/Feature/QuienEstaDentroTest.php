<?php

namespace Tests\Feature;

use App\Livewire\Marcar;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Marcaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tocar los contadores de la puerta para ver QUIÉNES están dentro, no solo cuántos.
 *
 * El número de arriba servía para mirarlo de reojo, pero no contestaba la pregunta que se hace de
 * verdad: a las siete de la tarde quedan tres marcados dentro, ¿quiénes son? Antes eso solo salía
 * del registro, filtrando a mano.
 */
class QuienEstaDentroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entrandoComo();
    }

    private function persona(string $cedula, string $nombre, string $tipo = Persona::TRABAJADOR, array $mas = []): Persona
    {
        return Persona::create(array_merge([
            'cedula' => $cedula,
            'tipo' => $tipo,
            'nombre' => $nombre,
            'dependencia' => 'Recursos Humanos',
            'activo' => true,
        ], $mas));
    }

    private function entra(Persona $persona, string $cuando, ?string $piso = null): void
    {
        Movimiento::create([
            'persona_id' => $persona->id,
            'tipo' => Movimiento::ENTRADA,
            'ocurrio_en' => $cuando,
            'piso' => $piso,
        ]);
    }

    private function sale(Persona $persona, string $cuando): void
    {
        Movimiento::create([
            'persona_id' => $persona->id,
            'tipo' => Movimiento::SALIDA,
            'ocurrio_en' => $cuando,
        ]);
    }

    public function test_la_lista_no_se_consulta_hasta_que_se_toca_el_contador(): void
    {
        $this->entra($this->persona('11111111', 'Ana Rodríguez'), now()->subHour());

        Livewire::test(Marcar::class)
            ->assertSet('viendoDentro', null)
            ->assertSee('Trabajadores')
            ->assertDontSee('Ana Rodríguez');
    }

    public function test_al_tocar_trabajadores_salen_los_que_estan_dentro(): void
    {
        $this->entra($this->persona('11111111', 'Ana Rodríguez'), now()->subHours(3));
        $this->entra($this->persona('22222222', 'Luis Pérez'), now()->subHour());

        Livewire::test(Marcar::class)
            ->call('verDentro', 'trabajador')
            ->assertSet('viendoDentro', 'trabajador')
            ->assertSee('Ana Rodríguez')
            ->assertSee('Luis Pérez')
            ->assertSee('V-11111111')
            ->assertSee('Recursos Humanos');
    }

    public function test_quien_ya_salio_no_aparece(): void
    {
        $sefue = $this->persona('33333333', 'Carmen Silva');
        $this->entra($sefue, now()->subHours(4));
        $this->sale($sefue, now()->subHour());

        Livewire::test(Marcar::class)
            ->call('verDentro', 'trabajador')
            ->assertDontSee('Carmen Silva');
    }

    public function test_trabajadores_y_visitantes_son_listas_distintas(): void
    {
        $this->entra($this->persona('11111111', 'Ana Rodríguez'), now()->subHour());
        $this->entra($this->persona('44444444', 'Pedro Visitante', Persona::INVITADO), now()->subMinutes(30), piso: '3');

        Livewire::test(Marcar::class)
            ->call('verDentro', 'trabajador')
            ->assertSee('Ana Rodríguez')
            ->assertDontSee('Pedro Visitante')
            ->call('verDentro', 'invitado')
            ->assertSee('Pedro Visitante')
            ->assertSee('Piso 3')
            ->assertDontSee('Ana Rodríguez');
    }

    public function test_tocar_el_mismo_contador_otra_vez_cierra_la_lista(): void
    {
        $this->entra($this->persona('11111111', 'Ana Rodríguez'), now()->subHour());

        Livewire::test(Marcar::class)
            ->call('verDentro', 'trabajador')
            ->assertSee('Ana Rodríguez')
            ->call('verDentro', 'trabajador')
            ->assertSet('viendoDentro', null)
            ->assertDontSee('Ana Rodríguez');
    }

    public function test_con_nadie_dentro_lo_dice_en_vez_de_quedarse_en_blanco(): void
    {
        Livewire::test(Marcar::class)
            ->call('verDentro', 'invitado')
            ->assertSee('No hay ningún visitante marcado dentro');
    }

    public function test_van_ordenados_del_que_lleva_mas_tiempo_dentro_al_ultimo_en_llegar(): void
    {
        $this->entra($this->persona('11111111', 'Ana Rodríguez'), now()->subHour());
        $this->entra($this->persona('22222222', 'Luis Pérez'), now()->subHours(5));

        $dentro = app(Marcaje::class)->quienesEstanDentro(Persona::TRABAJADOR);

        $this->assertSame(['Luis Pérez', 'Ana Rodríguez'], $dentro->pluck('nombre')->all());
    }

    public function test_al_invitado_se_le_busca_por_el_piso_que_visitaba_y_al_trabajador_por_su_dependencia(): void
    {
        $this->entra($this->persona('11111111', 'Ana Rodríguez'), now()->subHour());
        $this->entra($this->persona('44444444', 'Pedro Visitante', Persona::INVITADO), now()->subHour(), piso: '3');

        $marcaje = app(Marcaje::class);

        $this->assertSame('Recursos Humanos', $marcaje->quienesEstanDentro(Persona::TRABAJADOR)->first()['donde']);
        $this->assertSame('Piso 3', $marcaje->quienesEstanDentro(Persona::INVITADO)->first()['donde']);
    }

    public function test_al_marcar_a_alguien_la_lista_abierta_se_actualiza(): void
    {
        $ana = $this->persona('11111111', 'Ana Rodríguez');
        $this->entra($ana, now()->subHours(3));

        Livewire::test(Marcar::class)
            ->call('verDentro', 'trabajador')
            ->assertSee('Ana Rodríguez')
            ->set('cedula', '11111111')
            ->call('buscar')
            ->call('marcarSalida')
            // El nombre sigue en pantalla, pero en el mensaje de «salida registrada»: lo que se
            // comprueba es la LISTA, que ya no debería tener a nadie.
            ->assertSee('No hay ningún trabajador marcado dentro');
    }
}
