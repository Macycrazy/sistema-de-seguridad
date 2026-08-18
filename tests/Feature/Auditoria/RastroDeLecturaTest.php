<?php

namespace Tests\Feature\Auditoria;

use App\Livewire\Ingresar;
use App\Models\Bitacora;
use App\Models\Persona;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Usuarios\Rol;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El rastro de sesión y de lectura, integrado del trabajo paralelo de Deiber Sella: quién entró,
 * quién lo intentó sin lograrlo, y quién miró los datos de quién.
 */
class RastroDeLecturaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function entrar_bien_deja_rastro_a_nombre_de_quien_entro(): void
    {
        $usuario = User::factory()->create(['usuario' => 'ana', 'rol' => Rol::SUPERVISOR]);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'ana')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar');

        $this->assertDatabaseHas('bitacora', [
            'usuario_id' => $usuario->id,
            'accion' => Auditoria::INGRESO_CORRECTO,
        ]);
    }

    #[Test]
    public function clave_incorrecta_deja_rastro_sin_usuario(): void
    {
        User::factory()->create(['usuario' => 'ana']);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'ana')
            ->set('clave', 'la-que-no-es')
            ->call('entrar')
            ->assertHasErrors('usuario');

        $this->assertSame(1, Bitacora::query()
            ->where('accion', Auditoria::INGRESO_FALLIDO)
            ->whereNull('usuario_id')
            ->whereNull('sobre')
            ->count());
    }

    #[Test]
    public function cuenta_desactivada_deja_rastro_con_la_cuenta(): void
    {
        User::factory()->create(['usuario' => 'retirado', 'activo' => false]);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'retirado')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar')
            ->assertHasErrors('usuario');

        $this->assertDatabaseHas('bitacora', [
            'accion' => Auditoria::INGRESO_FALLIDO,
            'sobre' => 'retirado',
        ]);
    }

    #[Test]
    public function cerrar_sesion_deja_rastro(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('salir'))->assertRedirect(route('ingresar'));

        $this->assertDatabaseHas('bitacora', [
            'usuario_id' => $usuario->id,
            'accion' => Auditoria::SALIO,
        ]);
    }

    #[Test]
    public function consultar_una_cedula_deja_un_solo_rastro_por_racha(): void
    {
        $this->actingAs(User::factory()->create());

        // El tecleo dispara la misma consulta varias veces seguidas: una sola queda.
        app(Auditoria::class)->consultoCedula('12345678');
        app(Auditoria::class)->consultoCedula('12345678');
        app(Auditoria::class)->consultoCedula('12345678');

        $this->assertSame(1, Bitacora::where('accion', Auditoria::CONSULTO_CEDULA)->where('sobre', '12345678')->count());
    }

    #[Test]
    public function dos_cedulas_distintas_dejan_dos_rastros(): void
    {
        $this->actingAs(User::factory()->create());

        app(Auditoria::class)->consultoCedula('11111111');
        app(Auditoria::class)->consultoCedula('22222222');

        $this->assertSame(2, Bitacora::where('accion', Auditoria::CONSULTO_CEDULA)->count());
    }

    #[Test]
    public function ver_una_foto_deja_rastro(): void
    {
        $this->actingAs(User::factory()->create());
        $persona = Persona::create(['cedula' => '12345678', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA', 'activo' => true]);

        app(Auditoria::class)->vioFoto($persona);

        $this->assertDatabaseHas('bitacora', [
            'accion' => Auditoria::VIO_FOTO,
            'sobre' => '12345678',
        ]);
    }
}
