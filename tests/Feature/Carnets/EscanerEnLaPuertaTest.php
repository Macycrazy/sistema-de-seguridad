<?php

namespace Tests\Feature\Carnets;

use App\Livewire\Asociacion\Carnets;
use App\Livewire\Marcar;
use App\Models\User;
use App\Services\Puerta\AjustesDeLaPuerta;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El escáner del carnet se puede apagar sin desplegar nada.
 *
 * Teclear la cédula es lo que siempre funciona; escanear es un atajo, y un atajo que no encaja en
 * un puesto —sin cámara decente, a contraluz, con el carnets caído— estorba encima del campo que
 * sí sirve.
 */
class EscanerEnLaPuertaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function viene_encendido(): void
    {
        // Al revés que el reconocimiento facial: es el atajo probado, y sin él la puerta pierde lo
        // que la hace rápida con quien trae su carnet.
        $this->assertTrue(app(AjustesDeLaPuerta::class)->escanerDeCarnet());

        $this->entrandoComo();
        Livewire::test(Marcar::class)->assertSee('Escanear carnet con la cámara');
    }

    #[Test]
    public function apagado_la_puerta_solo_pide_la_cedula(): void
    {
        app(AjustesDeLaPuerta::class)->activarEscanerDeCarnet(false);

        $this->entrandoComo();

        Livewire::test(Marcar::class)
            ->assertDontSee('Escanear carnet con la cámara')
            ->assertSee('Introduce la cédula');
    }

    #[Test]
    public function se_apaga_desde_asociacion_con_carnets(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        Livewire::test(Carnets::class)
            ->assertSet('escanerEnLaPuerta', true)
            ->set('escanerEnLaPuerta', false)
            ->call('alternarEscaner')
            ->assertHasNoErrors();

        $this->assertFalse(app(AjustesDeLaPuerta::class)->escanerDeCarnet());
    }

    #[Test]
    public function el_supervisor_no_lo_toca(): void
    {
        // Esa pantalla es de quien administra los ajustes: apagar el escáner cambia lo que ve todo
        // el turno.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $this->get(route('asociacion'))->assertForbidden();
    }
}
