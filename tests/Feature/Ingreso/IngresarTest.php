<?php

namespace Tests\Feature\Ingreso;

use App\Http\Middleware\ExigirUsuarioActivo;
use App\Livewire\Ingresar;
use App\Models\User;
use App\Usuarios\Rol;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La puerta del sistema.
 *
 * Lo que se comprueba aquí no es que el formulario funcione, sino que la puerta cierre: que sin
 * sesión no se vea nada, que un mensaje de error no cuente si un usuario existe, y que
 * desactivar a alguien lo eche de verdad.
 */
class IngresarTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $atributos = []): User
    {
        return User::factory()->create($atributos);
    }

    #[Test]
    public function la_puerta_se_ve_sin_haber_entrado(): void
    {
        $this->get('/ingresar')
            ->assertOk()
            ->assertSeeLivewire(Ingresar::class);
    }

    #[Test]
    public function sin_sesion_no_se_ve_ninguna_pantalla_del_sistema(): void
    {
        $this->get('/')->assertRedirect('/ingresar');
        $this->get('/marcar')->assertRedirect('/ingresar');
        $this->get('/registro')->assertRedirect('/ingresar');
        $this->get('/diseno')->assertRedirect('/ingresar');
    }

    #[Test]
    public function quien_ya_entro_no_vuelve_a_ver_la_puerta(): void
    {
        $this->actingAs($this->usuario())
            ->get('/ingresar')
            ->assertRedirect('/');
    }

    #[Test]
    public function con_el_usuario_y_la_clave_buenos_se_entra(): void
    {
        $usuario = $this->usuario(['usuario' => 'vigilante', 'rol' => Rol::VIGILANTE]);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'vigilante')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
    }

    #[Test]
    public function se_entra_aunque_el_telefono_ponga_mayusculas_o_espacios(): void
    {
        // El usuario se guarda en minúsculas; el teclado del teléfono capitaliza la primera letra
        // y a veces deja un espacio. Aun así se entra: el login normaliza lo tecleado.
        $usuario = $this->usuario(['usuario' => 'j.perez', 'rol' => Rol::VIGILANTE]);

        Livewire::test(Ingresar::class)
            ->set('usuario', '  J.Perez ')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
    }

    #[Test]
    public function el_vigilante_cae_en_la_pantalla_que_va_a_usar_todo_el_turno(): void
    {
        $this->usuario(['usuario' => 'vigilante', 'rol' => Rol::VIGILANTE]);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'vigilante')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar')
            ->assertRedirect(route('marcar', absolute: false));
    }

    #[Test]
    public function los_demas_caen_en_el_inicio(): void
    {
        $this->usuario(['usuario' => 'jefa', 'rol' => Rol::ADMINISTRADOR]);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'jefa')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar')
            ->assertRedirect(route('inicio', absolute: false));
    }

    #[Test]
    public function con_la_clave_mala_no_se_entra(): void
    {
        $this->usuario(['usuario' => 'vigilante']);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'vigilante')
            ->set('clave', 'la-que-no-es')
            ->call('entrar')
            ->assertHasErrors('usuario');

        $this->assertGuest();
    }

    /**
     * El mensaje tiene que ser el mismo se equivoque en lo que se equivoque. Si con un usuario
     * que no existe dijera algo distinto, probar nombres de usuario hasta dar con uno bueno
     * sería cuestión de leer los mensajes.
     */
    #[Test]
    public function el_mensaje_no_delata_si_ese_usuario_existe(): void
    {
        $this->usuario(['usuario' => 'vigilante']);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'vigilante')
            ->set('clave', 'la-que-no-es')
            ->call('entrar')
            ->assertSee('Usuario o clave incorrectos.');

        Livewire::test(Ingresar::class)
            ->set('usuario', 'ese-no-existe')
            ->set('clave', 'la-que-no-es')
            ->call('entrar')
            ->assertSee('Usuario o clave incorrectos.');
    }

    #[Test]
    public function un_usuario_desactivado_no_entra_aunque_sepa_su_clave(): void
    {
        $this->usuario(['usuario' => 'exvigilante', 'activo' => false]);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'exvigilante')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar')
            ->assertHasErrors('usuario')
            ->assertSee('Ese usuario está desactivado.');

        $this->assertGuest();
    }

    /**
     * Comprobar «activo» solo al entrar no alcanza: quien ya estuviera dentro seguiría dentro.
     * Desactivar a alguien es justo lo que se hace cuando hay prisa por quitarle el acceso.
     */
    #[Test]
    public function al_desactivado_se_le_echa_en_la_siguiente_peticion(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)->get('/marcar')->assertOk();

        $usuario->update(['activo' => false]);

        $this->get('/marcar')->assertRedirect(route('ingresar'));
        $this->assertGuest();
    }

    /**
     * Lo anterior comprueba la navegación. Las acciones de Livewire no vuelven a pasar por la ruta
     * de la pantalla, así que hay que asegurarse de que el middleware que echa al desactivado se
     * aplique también a la ruta por la que Livewire manda todo. Se comprueba sobre el router, que
     * es donde está la respuesta, y no razonándolo.
     */
    #[Test]
    public function la_ruta_de_livewire_tambien_pasa_por_ese_middleware(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => str_contains($ruta->uri(), 'livewire') && str_contains($ruta->uri(), 'update'));

        $this->assertNotEmpty($rutas, 'No se encontró la ruta por la que Livewire manda sus acciones.');

        foreach ($rutas as $ruta) {
            $this->assertContains(
                ExigirUsuarioActivo::class,
                app('router')->gatherRouteMiddleware($ruta),
                "«{$ruta->uri()}» no pasa por ExigirUsuarioActivo.",
            );
        }
    }

    #[Test]
    public function despues_de_insistir_hay_que_esperar(): void
    {
        $this->usuario(['usuario' => 'vigilante']);

        $componente = Livewire::test(Ingresar::class)
            ->set('usuario', 'vigilante')
            ->set('clave', 'la-que-no-es');

        for ($intento = 0; $intento < Ingresar::INTENTOS_MAXIMOS; $intento++) {
            $componente->call('entrar')->assertSee('Usuario o clave incorrectos.');
        }

        // El siguiente ya ni compara: aunque la clave sea la buena, toca esperar.
        $componente
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar')
            ->assertSee('Demasiados intentos');

        $this->assertGuest();
    }

    /**
     * El identificador de sesión con el que se llegó a la pantalla no puede seguir sirviendo
     * después de entrar: si alguien lo conociera de antes, tendría la sesión de quien acaba de
     * entrar. Lo hace Auth::login() por dentro; esto es para que nadie lo quite sin darse cuenta.
     */
    #[Test]
    public function al_entrar_cambia_el_identificador_de_sesion(): void
    {
        $this->usuario(['usuario' => 'vigilante']);

        $this->get('/ingresar');
        $anterior = session()->getId();

        Livewire::test(Ingresar::class)
            ->set('usuario', 'vigilante')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar');

        $this->assertNotSame($anterior, session()->getId());
    }

    #[Test]
    public function salir_cierra_la_sesion(): void
    {
        $this->actingAs($this->usuario())
            ->post(route('salir'))
            ->assertRedirect(route('ingresar'));

        $this->assertGuest();
    }

    #[Test]
    public function salir_no_se_puede_disparar_con_un_enlace(): void
    {
        // Un GET lo dispara cualquier cosa que cargue una URL, y sacar a un vigilante de su
        // turno a mitad de un marcaje no es un chiste.
        $this->actingAs($this->usuario())
            ->get('/salir')
            ->assertStatus(405);

        $this->assertAuthenticated();
    }

    #[Test]
    public function la_clave_no_se_guarda_en_claro(): void
    {
        $usuario = $this->usuario(['password' => 'clave-en-claro']);

        $this->assertNotSame('clave-en-claro', $usuario->password);
        $this->assertTrue(Hash::check('clave-en-claro', $usuario->password));
    }

    /** Igual que en «personas»: si no se normaliza, las dos tablas hablan de números distintos. */
    #[Test]
    public function la_cedula_del_usuario_se_guarda_en_solo_digitos(): void
    {
        $usuario = $this->usuario(['cedula' => 'V-12.345.678']);

        $this->assertSame('12345678', $usuario->fresh()->cedula);
    }
}
