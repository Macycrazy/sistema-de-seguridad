<?php

namespace Tests\Feature\Pases;

use App\Livewire\Pases\ListaDePases;
use App\Models\EntregaDePase;
use App\Models\Movimiento;
use App\Models\Pase;
use App\Models\Persona;
use App\Models\User;
use App\Services\Pases\Pases;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaPasesTest extends TestCase
{
    use RefreshDatabase;

    private function visitante(): Persona
    {
        return Persona::create([
            'cedula' => '11111111', 'tipo' => Persona::INVITADO,
            'nombre' => 'ANA PÉREZ', 'motivo' => 'REUNIÓN', 'activo' => true,
        ]);
    }

    #[Test]
    public function el_vigilante_no_entra(): void
    {
        // Los pases los lleva la recepción, no la puerta: el vigilante los entrega al marcar, pero
        // el catálogo no es suyo.
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
        $this->get(route('pases'))->assertForbidden();
    }

    #[Test]
    public function el_supervisor_entra_porque_es_quien_los_lleva(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $this->get(route('pases'))->assertOk();
    }

    #[Test]
    public function una_tanda_se_carga_desde_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        Livewire::test(ListaDePases::class)
            ->call('abrirTanda')
            ->set('prefijoTanda', 'V-')
            ->set('desdeTanda', '1')
            ->set('hastaTanda', '5')
            ->call('guardarTanda')
            ->assertHasNoErrors()
            ->assertSee('Cargados 5 pases');

        $this->assertSame(5, Pase::count());
    }

    #[Test]
    public function la_pantalla_dice_quien_lleva_cada_pase(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $pase = Pase::create(['codigo' => 'V-01']);
        app(Pases::class)->entregar($pase, $this->visitante());

        Livewire::test(ListaDePases::class)
            ->assertSee('V-01')
            ->assertSee('Fuera')
            ->assertSee('ANA PÉREZ');
    }

    #[Test]
    public function se_recupera_un_pase_que_aparecio_sin_que_nadie_marcara_la_salida(): void
    {
        // Pasa: el pase aparece en el mostrador, en un cajón, al cerrar el turno.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $entrega = app(Pases::class)->entregar(Pase::create(['codigo' => 'V-01']), $this->visitante());

        Livewire::test(ListaDePases::class)
            ->call('recuperar', $entrega->id)
            ->assertHasNoErrors();

        $this->assertNotNull($entrega->fresh()->devuelto_en);
    }

    #[Test]
    public function se_entrega_un_pase_a_quien_ya_estaba_dentro(): void
    {
        // El sistema no empieza de cero: cuando se cargan los pases ya hay visitantes dentro a los
        // que nadie les dio ninguno, y hay que poder ponerse al día sin marcarles otra entrada.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $ana = $this->visitante();
        $pase = Pase::create(['codigo' => 'V-01']);

        Livewire::test(ListaDePases::class)
            ->call('abrirEntrega')
            ->set('cedulaEntrega', '11111111')
            ->set('paseEntrega', (string) $pase->id)
            ->call('entregar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entregas_de_pase', [
            'pase_id' => $pase->id,
            'persona_id' => $ana->id,
            'devuelto_en' => null,
        ]);
    }

    #[Test]
    public function no_se_entrega_un_pase_a_una_cedula_que_no_esta_en_el_sistema(): void
    {
        // A alguien que no consta se le marca primero en la puerta: dar el pase antes dejaría el
        // pase fuera sin nadie a quien pedírselo.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $pase = Pase::create(['codigo' => 'V-01']);

        Livewire::test(ListaDePases::class)
            ->call('abrirEntrega')
            ->set('cedulaEntrega', '99999999')
            ->set('paseEntrega', (string) $pase->id)
            ->call('entregar')
            ->assertHasErrors('cedulaEntrega');

        $this->assertSame(0, EntregaDePase::count());
    }

    #[Test]
    public function la_pantalla_lista_a_los_visitantes_que_estan_dentro_sin_pase(): void
    {
        // Es la lista de ponerse al día el día que se cargan los pases, y después destapa al
        // visitante al que se le olvidó dárselo.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $ana = $this->visitante();
        Movimiento::create([
            'persona_id' => $ana->id,
            'tipo' => Movimiento::ENTRADA,
            'ocurrio_en' => now()->subHour(),
        ]);

        Livewire::test(ListaDePases::class)
            ->assertSee('visitante dentro sin pase')
            ->assertSee('ANA PÉREZ')
            // Y el botón deja la entrega apuntando a ella.
            ->call('darPaseA', '11111111')
            ->assertSet('entregando', true)
            ->assertSet('cedulaEntrega', '11111111');
    }

    #[Test]
    public function quien_ya_lleva_pase_o_ya_se_fue_no_sale_en_esa_lista(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        // Ana está dentro y ya lleva pase.
        $ana = $this->visitante();
        Movimiento::create(['persona_id' => $ana->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => now()->subHours(2)]);
        app(Pases::class)->entregar(Pase::create(['codigo' => 'V-01']), $ana);

        // Luis entró y ya salió.
        $luis = Persona::create(['cedula' => '22222222', 'tipo' => Persona::INVITADO, 'nombre' => 'LUIS GÓMEZ', 'motivo' => 'X', 'activo' => true]);
        Movimiento::create(['persona_id' => $luis->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => now()->subHours(3)]);
        Movimiento::create(['persona_id' => $luis->id, 'tipo' => Movimiento::SALIDA, 'ocurrio_en' => now()->subHour()]);

        Livewire::test(ListaDePases::class)->assertDontSee('visitante dentro sin pase');
    }

    #[Test]
    public function un_pase_entregado_no_se_puede_quitar_desde_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $pase = Pase::create(['codigo' => 'V-01']);
        app(Pases::class)->entregar($pase, $this->visitante());

        Livewire::test(ListaDePases::class)
            ->call('eliminar', $pase->id)
            ->assertHasErrors('pase');

        $this->assertSame(1, Pase::count());
    }
}
