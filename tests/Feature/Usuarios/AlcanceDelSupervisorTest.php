<?php

namespace Tests\Feature\Usuarios;

use App\Livewire\Usuarios\ListaDeUsuarios;
use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nadie toca ni asciende a quien esté por encima de su propio rol.
 *
 * Esta es la regla que sostiene que la gestión de usuarios se le pueda dar al supervisor. Sin
 * ella, la pantalla sería una escalera de dos escalones: me creo un administrador, o le pongo otra
 * clave a uno que ya exista, y entro con él.
 *
 * Todo se prueba llamando las acciones directamente, sin pasar por los botones: en la pantalla los
 * botones ni se dibujan, pero eso es cortesía y no seguridad.
 */
class AlcanceDelSupervisorTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'la-que-yo-quiera';

    private function supervisor(): User
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor);

        return $supervisor;
    }

    #[Test]
    public function el_supervisor_abre_la_pantalla_de_usuarios(): void
    {
        $this->supervisor();

        $this->get('/usuarios')->assertOk();
        Livewire::test(ListaDeUsuarios::class)->assertOk();
    }

    #[Test]
    public function el_selector_no_le_ofrece_al_supervisor_el_rol_de_administrador(): void
    {
        $this->supervisor();

        $roles = Livewire::test(ListaDeUsuarios::class)->instance()->roles();

        $this->assertSame(['vigilante', 'supervisor'], array_keys($roles));
    }

    #[Test]
    public function el_supervisor_no_se_crea_un_administrador(): void
    {
        $this->supervisor();

        Livewire::test(ListaDeUsuarios::class)
            ->set('usuario', 'colado')
            ->set('nombre', 'El Colado')
            ->set('rol', Rol::administrador()->value)
            ->set('clave', self::CLAVE)
            ->call('crear')
            ->assertHasErrors('rol');

        $this->assertDatabaseMissing('users', ['usuario' => 'colado']);
    }

    #[Test]
    public function el_supervisor_no_le_pone_la_clave_a_un_administrador(): void
    {
        $this->supervisor();
        $jefa = User::factory()->administrador()->create();
        $laDeAntes = $jefa->password;

        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirCambioDeClave', $jefa->id)
            ->set('claveNueva', 'me-la-pongo-yo')
            ->call('guardarCambioDeClave')
            ->assertHasErrors('usuario');

        $this->assertSame($laDeAntes, $jefa->fresh()->password);
    }

    #[Test]
    public function el_supervisor_no_desactiva_a_un_administrador(): void
    {
        $this->supervisor();
        $jefa = User::factory()->administrador()->create();

        Livewire::test(ListaDeUsuarios::class)
            ->call('desactivar', $jefa->id)
            ->assertHasErrors('usuario');

        $this->assertTrue($jefa->fresh()->activo);
    }

    #[Test]
    public function el_supervisor_no_asciende_a_nadie_a_administrador(): void
    {
        $this->supervisor();
        $vigilante = User::factory()->create();

        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirCambioDeRol', $vigilante->id)
            ->set('rolNuevo', Rol::administrador()->value)
            ->call('guardarCambioDeRol')
            ->assertHasErrors('rol');

        $this->assertSame(Rol::vigilante(), $vigilante->fresh()->rol);
    }

    #[Test]
    public function el_supervisor_si_gestiona_vigilantes_y_a_otros_supervisores(): void
    {
        $this->supervisor();
        $vigilante = User::factory()->create();
        $companero = User::factory()->supervisor()->create();

        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirCambioDeClave', $vigilante->id)
            ->set('claveNueva', 'se-la-dicto-yo')
            ->call('guardarCambioDeClave')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('se-la-dicto-yo', $vigilante->fresh()->password));

        Livewire::test(ListaDeUsuarios::class)
            ->call('desactivar', $companero->id)
            ->assertHasNoErrors();

        $this->assertFalse($companero->fresh()->activo);
    }

    #[Test]
    public function el_administrador_si_gestiona_a_otro_administrador(): void
    {
        $this->actingAs(User::factory()->administrador()->create());
        $otra = User::factory()->administrador()->create();

        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirCambioDeClave', $otra->id)
            ->set('claveNueva', 'la-cambio-yo')
            ->call('guardarCambioDeClave')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('la-cambio-yo', $otra->fresh()->password));
    }

    #[Test]
    public function nadie_se_cambia_el_rol_a_si_mismo(): void
    {
        $supervisor = $this->supervisor();

        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirCambioDeRol', $supervisor->id)
            ->set('rolNuevo', Rol::vigilante()->value)
            ->call('guardarCambioDeRol')
            ->assertHasErrors('rol');

        $this->assertSame(Rol::supervisor(), $supervisor->fresh()->rol);
    }

    #[Test]
    public function no_se_le_baja_el_rol_al_ultimo_administrador(): void
    {
        $this->actingAs(User::factory()->administrador()->create());
        $solitaria = User::factory()->administrador()->create();

        // Con dos administradores activos, bajar a uno pasa.
        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirCambioDeRol', $solitaria->id)
            ->set('rolNuevo', Rol::supervisor()->value)
            ->call('guardarCambioDeRol')
            ->assertHasNoErrors();

        $this->assertSame(Rol::supervisor(), $solitaria->fresh()->rol);
    }
}
