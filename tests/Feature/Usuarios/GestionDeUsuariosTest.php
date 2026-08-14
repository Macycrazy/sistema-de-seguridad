<?php

namespace Tests\Feature\Usuarios;

use App\Livewire\Ingresar;
use App\Livewire\Usuarios\ListaDeUsuarios;
use App\Models\User;
use App\Services\GestionDeUsuarios;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La pantalla de usuarios: crear, desactivar y cambiar claves.
 *
 * Lo que más se comprueba aquí no es que funcione, sino que no deje al sistema sin salida: nadie
 * se desactiva a sí mismo, y el último administrador activo no se puede desactivar. Un clic que
 * deja al sistema sin quien lo administre no se arregla desde ninguna pantalla.
 */
class GestionDeUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'la-que-yo-quiera';

    private function administrador(): User
    {
        $administrador = User::factory()->administrador()->create();

        $this->actingAs($administrador);

        return $administrador;
    }

    #[Test]
    public function el_administrador_da_de_alta_a_alguien(): void
    {
        $this->administrador();

        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirFormulario')
            ->set('usuario', 'jmartinez')
            ->set('nombre', 'José Martínez Rojas')
            ->set('cedula', 'V-12.345.678')
            ->set('rol', Rol::VIGILANTE->value)
            ->set('clave', self::CLAVE)
            ->call('crear')
            ->assertHasNoErrors();

        $creado = User::where('usuario', 'jmartinez')->firstOrFail();

        $this->assertSame('José Martínez Rojas', $creado->nombre);
        $this->assertSame('12345678', $creado->cedula);
        $this->assertSame(Rol::VIGILANTE, $creado->rol);
        $this->assertTrue($creado->activo);
        $this->assertTrue(Hash::check(self::CLAVE, $creado->password));
    }

    /**
     * La clave que puso el administrador sirve tal cual: se entra con ella y se sigue trabajando.
     * El sistema no manda a cambiarla —así lo decidió el CIIP—; cambiarla es cosa de su dueño,
     * cuando quiera, desde su nombre en el encabezado.
     */
    #[Test]
    public function con_la_clave_que_puso_el_administrador_se_entra_y_se_sigue(): void
    {
        $this->administrador();

        Livewire::test(ListaDeUsuarios::class)
            ->set('usuario', 'jmartinez')
            ->set('nombre', 'José Martínez Rojas')
            ->set('rol', Rol::VIGILANTE->value)
            ->set('clave', self::CLAVE)
            ->call('crear')
            ->assertHasNoErrors();

        auth()->logout();

        Livewire::test(Ingresar::class)
            ->set('usuario', 'jmartinez')
            ->set('clave', self::CLAVE)
            ->call('entrar')
            ->assertHasNoErrors();

        $this->assertAuthenticated();

        // Y llega a su pantalla sin que nada lo desvíe a cambiar la clave.
        $this->get('/marcar')->assertOk();
    }

    /**
     * La clave no se escribe nunca en la pantalla. La tecleó el administrador, ya la sabe, y una
     * clave escrita en la pantalla de un puesto de vigilancia la lee cualquiera que pase.
     */
    #[Test]
    public function la_clave_no_se_repite_en_pantalla_ni_se_guarda_en_claro(): void
    {
        $this->administrador();

        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirFormulario')
            ->set('usuario', 'jmartinez')
            ->set('nombre', 'José Martínez Rojas')
            ->set('rol', Rol::VIGILANTE->value)
            ->set('clave', self::CLAVE)
            ->call('crear')
            ->assertDontSee(self::CLAVE)
            ->assertSet('clave', '');

        $creado = User::where('usuario', 'jmartinez')->firstOrFail();

        $this->assertNotSame(self::CLAVE, $creado->password);
        $this->assertTrue(Hash::check(self::CLAVE, $creado->password));
    }

    #[Test]
    public function sin_clave_no_se_da_de_alta_a_nadie(): void
    {
        $this->administrador();

        Livewire::test(ListaDeUsuarios::class)
            ->set('usuario', 'jmartinez')
            ->set('nombre', 'José Martínez Rojas')
            ->set('rol', Rol::VIGILANTE->value)
            ->call('crear')
            ->assertHasErrors('clave');

        $this->assertDatabaseMissing('users', ['usuario' => 'jmartinez']);
    }

    #[Test]
    public function la_clave_del_alta_tiene_un_minimo(): void
    {
        $this->administrador();

        Livewire::test(ListaDeUsuarios::class)
            ->set('usuario', 'jmartinez')
            ->set('nombre', 'José Martínez Rojas')
            ->set('rol', Rol::VIGILANTE->value)
            ->set('clave', 'corta')
            ->call('crear')
            ->assertHasErrors('clave');

        $this->assertDatabaseMissing('users', ['usuario' => 'jmartinez']);
    }

    #[Test]
    public function el_administrador_le_cambia_la_clave_a_alguien_de_la_lista(): void
    {
        $this->administrador();
        $vigilante = User::factory()->create();

        $componente = Livewire::test(ListaDeUsuarios::class)
            ->call('abrirCambioDeClave', $vigilante->id)
            ->assertSet('cambiandoClaveA', $vigilante->id)
            ->set('claveNueva', 'se-la-dicto-yo')
            ->call('guardarCambioDeClave')
            ->assertHasNoErrors();

        $vigilante->refresh();

        $this->assertTrue(Hash::check('se-la-dicto-yo', $vigilante->password));

        // El campo se cierra y la clave no queda escrita por ninguna parte de la pantalla.
        $componente
            ->assertSet('cambiandoClaveA', null)
            ->assertSet('claveNueva', '')
            ->assertDontSee('se-la-dicto-yo');
    }

    #[Test]
    public function la_clave_cambiada_desde_la_lista_tiene_el_mismo_minimo(): void
    {
        $this->administrador();
        $vigilante = User::factory()->create();
        $laDeAntes = $vigilante->password;

        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirCambioDeClave', $vigilante->id)
            ->set('claveNueva', 'corta')
            ->call('guardarCambioDeClave')
            ->assertHasErrors('claveNueva');

        $this->assertSame($laDeAntes, $vigilante->fresh()->password);
    }

    #[Test]
    public function no_se_repite_un_nombre_de_usuario(): void
    {
        $this->administrador();
        User::factory()->create(['usuario' => 'jmartinez']);

        Livewire::test(ListaDeUsuarios::class)
            ->set('usuario', 'jmartinez')
            ->set('nombre', 'Otro José')
            ->set('rol', Rol::VIGILANTE->value)
            ->set('clave', self::CLAVE)
            ->call('crear')
            ->assertHasErrors('usuario');

        $this->assertSame(1, User::where('usuario', 'jmartinez')->count());
    }

    #[Test]
    public function un_rol_que_no_existe_no_pasa_aunque_llegue_por_livewire(): void
    {
        $this->administrador();

        Livewire::test(ListaDeUsuarios::class)
            ->set('usuario', 'jmartinez')
            ->set('nombre', 'José Martínez Rojas')
            ->set('rol', 'jefazo')
            ->set('clave', self::CLAVE)
            ->call('crear')
            ->assertHasErrors('rol');

        $this->assertDatabaseMissing('users', ['usuario' => 'jmartinez']);
    }

    #[Test]
    public function nadie_se_desactiva_a_si_mismo(): void
    {
        $administrador = $this->administrador();
        User::factory()->administrador()->create();

        Livewire::test(ListaDeUsuarios::class)
            ->call('desactivar', $administrador->id)
            ->assertHasErrors('usuario');

        $this->assertTrue($administrador->fresh()->activo);
    }

    #[Test]
    public function a_otro_administrador_si_se_le_puede_desactivar(): void
    {
        $this->administrador();
        $otro = User::factory()->administrador()->create();

        Livewire::test(ListaDeUsuarios::class)
            ->call('desactivar', $otro->id)
            ->assertHasNoErrors();

        $this->assertFalse($otro->fresh()->activo);
        $this->assertSame(1, app(GestionDeUsuarios::class)->administradoresActivos());
    }

    /**
     * El servicio no deja al sistema sin administradores.
     *
     * Por la pantalla a esta regla no se llega, y es a propósito: quien la usa es siempre un
     * administrador activo, así que si el que va a desactivar es otro, quedan dos; y si es él
     * mismo, lo corta antes la regla de no desactivarse a uno mismo. La regla está igual porque
     * el servicio lo usan también el comando de consola y lo que venga después, y quedarse sin
     * administradores no se arregla desde ninguna pantalla: hay que entrar a la base a mano.
     */
    #[Test]
    public function el_servicio_no_deja_al_sistema_sin_administradores(): void
    {
        $solitario = User::factory()->administrador()->create();

        $this->expectException(ValidationException::class);

        app(GestionDeUsuarios::class)->desactivar($solitario, User::factory()->create());
    }

    #[Test]
    public function desactivar_no_borra_a_nadie(): void
    {
        $this->administrador();
        $vigilante = User::factory()->create();
        $cuantos = User::count();

        Livewire::test(ListaDeUsuarios::class)->call('desactivar', $vigilante->id);

        // Sigue estando: si se borrara, el rastro de la auditoría apuntaría al vacío.
        $this->assertSame($cuantos, User::count());
        $this->assertFalse($vigilante->fresh()->activo);

        Livewire::test(ListaDeUsuarios::class)->call('reactivar', $vigilante->id);

        $this->assertTrue($vigilante->fresh()->activo);
    }
}
