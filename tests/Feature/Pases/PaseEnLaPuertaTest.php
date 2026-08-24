<?php

namespace Tests\Feature\Pases;

use App\Livewire\Marcar;
use App\Models\EntregaDePase;
use App\Models\Movimiento;
use App\Models\Pase;
use App\Models\Persona;
use App\Services\Marcaje;
use App\Services\Pases\Pases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El pase entregado en el mismo gesto de marcar al visitante.
 *
 * En la puerta no hay un segundo momento para nada: si dar el pase fuera otra pantalla, con cola
 * detrás no se haría, y los pases se perderían igual que se perdían los conductores.
 */
class PaseEnLaPuertaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entrandoComo();
    }

    private function visitante(): Persona
    {
        return Persona::create([
            'cedula' => '11111111',
            'tipo' => Persona::INVITADO,
            'nombre' => 'ANA PÉREZ',
            'motivo' => 'REUNIÓN',
            'piso' => '2-1',
            'activo' => true,
        ]);
    }

    private function trabajador(): Persona
    {
        return Persona::create([
            'cedula' => '22222222',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'LUIS GÓMEZ',
            'activo' => true,
        ]);
    }

    private function pase(string $codigo = 'V-01'): Pase
    {
        return Pase::create(['codigo' => $codigo]);
    }

    #[Test]
    public function al_marcar_la_entrada_de_un_visitante_se_le_entrega_el_pase(): void
    {
        $ana = $this->visitante();
        $pase = $this->pase();

        Livewire::test(Marcar::class)
            ->set('cedula', '11111111')
            ->call('buscar')
            ->assertSee('¿Se le da pase?')
            ->set('paseEntrada', (string) $pase->id)
            ->call('marcarEntrada')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entregas_de_pase', [
            'pase_id' => $pase->id,
            'persona_id' => $ana->id,
            'devuelto_en' => null,
        ]);
    }

    #[Test]
    public function al_marcar_su_salida_el_pase_vuelve_solo(): void
    {
        $ana = $this->visitante();
        $pase = $this->pase();

        $componente = Livewire::test(Marcar::class)
            ->set('cedula', '11111111')
            ->call('buscar')
            ->set('paseEntrada', (string) $pase->id)
            ->call('marcarEntrada');

        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        $componente
            ->set('cedula', '11111111')
            ->call('buscar')
            ->assertSee('Devuelve el pase')
            ->call('marcarSalida')
            ->assertHasNoErrors();

        $this->assertNotNull(EntregaDePase::first()->devuelto_en);
        $this->assertCount(1, app(Pases::class)->libres(), 'Vuelve a estar disponible.');
    }

    #[Test]
    public function si_se_va_con_el_pase_puesto_queda_constando_que_sigue_fuera(): void
    {
        // Desmarcarlo no es un descuido: es decir la verdad. Darlo por devuelto sin serlo haría
        // que el sistema ofreciera un pase que no está.
        $ana = $this->visitante();
        $pase = $this->pase();

        $componente = Livewire::test(Marcar::class)
            ->set('cedula', '11111111')
            ->call('buscar')
            ->set('paseEntrada', (string) $pase->id)
            ->call('marcarEntrada');

        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        $componente
            ->set('cedula', '11111111')
            ->call('buscar')
            ->set('devuelvePase', false)
            ->call('marcarSalida')
            ->assertHasNoErrors();

        $this->assertNull(EntregaDePase::first()->devuelto_en);
        $this->assertCount(0, app(Pases::class)->libres());
    }

    #[Test]
    public function al_trabajador_no_se_le_ofrece_pase(): void
    {
        // Entra con su carnet: el pase es para quien viene de visita.
        $this->trabajador();
        $this->pase();

        Livewire::test(Marcar::class)
            ->set('cedula', '22222222')
            ->call('buscar')
            ->assertDontSee('¿Se le da pase?');
    }

    #[Test]
    public function sin_pases_cargados_la_puerta_no_dice_nada_de_pases(): void
    {
        $this->visitante();

        Livewire::test(Marcar::class)
            ->set('cedula', '11111111')
            ->call('buscar')
            ->assertDontSee('¿Se le da pase?');
    }

    #[Test]
    public function con_todos_los_pases_fuera_se_dice_en_vez_de_callarse(): void
    {
        // Que no quede ninguno libre no es lo mismo que no haber pases; sin decirlo, la pantalla
        // muda parecería que esto no funciona.
        $ana = $this->visitante();
        $otro = Persona::create(['cedula' => '33333333', 'tipo' => Persona::INVITADO, 'nombre' => 'OTRO', 'motivo' => 'X', 'activo' => true]);

        app(Pases::class)->entregar($this->pase(), $otro);

        Livewire::test(Marcar::class)
            ->set('cedula', '11111111')
            ->call('buscar')
            ->assertSee('No queda ningún pase libre');

        $this->assertNotNull($ana->id);
    }

    #[Test]
    public function marcar_sin_dar_pase_no_entrega_ninguno(): void
    {
        $this->visitante();
        $this->pase();

        Livewire::test(Marcar::class)
            ->set('cedula', '11111111')
            ->call('buscar')
            ->call('marcarEntrada')
            ->assertHasNoErrors();

        $this->assertSame(0, EntregaDePase::count());
        $this->assertDatabaseHas('movimientos', ['tipo' => Movimiento::ENTRADA]);
    }
}
