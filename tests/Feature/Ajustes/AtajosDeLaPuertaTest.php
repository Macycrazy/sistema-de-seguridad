<?php

namespace Tests\Feature\Ajustes;

use App\Livewire\Ajustes\AtajosDeLaPuerta;
use App\Livewire\Marcar;
use App\Models\User;
use App\Services\Puerta\AjustesDeLaPuerta;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Qué se le ofrece al vigilante, y la regla que impide dejarlo sin nada.
 */
class AtajosDeLaPuertaTest extends TestCase
{
    use RefreshDatabase;

    private function comoAdmin(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));
    }

    #[Test]
    public function la_cedula_y_el_carnet_vienen_encendidos_y_la_cara_no(): void
    {
        $ajustes = app(AjustesDeLaPuerta::class);

        $this->assertTrue($ajustes->tecleoDeCedula());
        $this->assertTrue($ajustes->escanerDeCarnet());
        $this->assertFalse($ajustes->reconocimientoFacial(), 'La cara se enciende cuando alguien se fíe.');
    }

    #[Test]
    public function se_puede_quitar_el_tecleo_si_queda_el_carnet(): void
    {
        // Un puesto donde todo el mundo pasa el carnet: el campo solo estorba.
        $this->comoAdmin();

        Livewire::test(AtajosDeLaPuerta::class)
            ->set('cedula', false)
            ->call('alternar', 'cedula')
            ->assertHasNoErrors();

        $this->assertFalse(app(AjustesDeLaPuerta::class)->tecleoDeCedula());

        $this->entrandoComo();
        Livewire::test(Marcar::class)
            ->assertDontSee('Introduce la cédula')
            ->assertSee('pasa el carnet por la cámara');
    }

    #[Test]
    public function no_se_pueden_apagar_los_dos_a_la_vez(): void
    {
        // La puerta se quedaría sin ninguna forma de marcar a nadie, y eso se descubriría en el
        // turno. Se dice y se deshace.
        $this->comoAdmin();

        app(AjustesDeLaPuerta::class)->activarEscanerDeCarnet(false);

        Livewire::test(AtajosDeLaPuerta::class)
            ->set('cedula', false)
            ->call('alternar', 'cedula')
            ->assertSet('cedula', true)
            ->assertSee('se quedaría sin ninguna forma de marcar');

        $this->assertTrue(app(AjustesDeLaPuerta::class)->tecleoDeCedula(), 'Sigue encendido.');
    }

    #[Test]
    public function la_cara_no_cuenta_como_forma_de_marcar(): void
    {
        // Se queda sin servir el día que alguien vacíe el índice de caras: no puede ser la única.
        $this->comoAdmin();

        app(AjustesDeLaPuerta::class)->activarReconocimientoFacial(true);
        app(AjustesDeLaPuerta::class)->activarEscanerDeCarnet(false);

        Livewire::test(AtajosDeLaPuerta::class)
            ->set('cedula', false)
            ->call('alternar', 'cedula')
            ->assertSet('cedula', true);
    }

    #[Test]
    public function quien_solo_puede_ver_los_ajustes_no_los_cambia(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $this->get(route('ajustes'))->assertForbidden();
    }
}
