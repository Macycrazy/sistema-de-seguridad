<?php

namespace Tests\Feature\Usuarios;

use App\Livewire\CambiarClave;
use App\Models\User;
use App\Services\GestionDeUsuarios;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cada quien cambia su clave cuando le parece, desde su nombre en el encabezado.
 *
 * El sistema no obliga a cambiar la que puso el administrador: así lo decidió el CIIP. Esta
 * pantalla es la que hace que una clave pase a ser de su dueño, cuando él quiera.
 */
class CambiarClaveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cualquiera_que_haya_entrado_llega_a_su_pantalla_de_clave(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('clave'))->assertOk();
    }

    #[Test]
    public function hay_que_saber_la_clave_actual_para_cambiarla(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $laDeAntes = $usuario->password;

        Livewire::test(CambiarClave::class)
            ->set('actual', 'esa-no-es')
            ->set('nueva', 'una-clave-larga')
            ->set('repetida', 'una-clave-larga')
            ->call('guardar')
            ->assertHasErrors('actual');

        $this->assertSame($laDeAntes, $usuario->fresh()->password);
    }

    #[Test]
    public function la_clave_nueva_tiene_un_minimo(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CambiarClave::class)
            ->set('actual', UserFactory::CLAVE)
            ->set('nueva', 'corta')
            ->set('repetida', 'corta')
            ->call('guardar')
            ->assertHasErrors('nueva');
    }

    #[Test]
    public function las_dos_claves_nuevas_tienen_que_coincidir(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CambiarClave::class)
            ->set('actual', UserFactory::CLAVE)
            ->set('nueva', 'una-clave-larga')
            ->set('repetida', 'otra-clave-larga')
            ->call('guardar')
            ->assertHasErrors('repetida');
    }

    #[Test]
    public function al_cambiarla_vale_la_nueva(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        Livewire::test(CambiarClave::class)
            ->set('actual', UserFactory::CLAVE)
            ->set('nueva', 'la-mia-y-de-nadie-mas')
            ->set('repetida', 'la-mia-y-de-nadie-mas')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Clave cambiada')
            ->assertSet('nueva', '');

        $this->assertTrue(Hash::check('la-mia-y-de-nadie-mas', $usuario->fresh()->password));
    }

    /**
     * La puerta de entrada tenía su límite de intentos y esta no, y es una puerta igual: quien se
     * encuentre una máquina con la sesión abierta podría probar claves hasta dar con la buena y
     * quedarse con ese usuario para siempre.
     */
    #[Test]
    public function despues_de_insistir_con_la_clave_actual_hay_que_esperar(): void
    {
        $this->actingAs(User::factory()->create());

        $componente = Livewire::test(CambiarClave::class)
            ->set('actual', 'esa-no-es')
            ->set('nueva', 'una-clave-larga')
            ->set('repetida', 'una-clave-larga');

        for ($intento = 0; $intento < CambiarClave::INTENTOS_MAXIMOS; $intento++) {
            $componente->call('guardar')->assertSee('Esa no es tu clave actual.');
        }

        $componente
            ->set('actual', UserFactory::CLAVE)
            ->call('guardar')
            ->assertSee('Demasiados intentos');
    }

    #[Test]
    public function el_minimo_es_el_que_dice_el_servicio(): void
    {
        $justa = str_repeat('a', GestionDeUsuarios::MINIMO_DE_LA_CLAVE);
        $this->actingAs(User::factory()->create());

        Livewire::test(CambiarClave::class)
            ->set('actual', UserFactory::CLAVE)
            ->set('nueva', $justa)
            ->set('repetida', $justa)
            ->call('guardar')
            ->assertHasNoErrors();
    }
}
