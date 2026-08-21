<?php

namespace Tests\Feature\Roles;

use App\Livewire\Registro\RegistroDelDia;
use App\Models\Persona;
use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cada rol ve lo suyo y nada más.
 *
 * Esto es la tabla del README escrita como prueba. Se comprueba en el servidor, que es lo único
 * que cuenta: esconder una tarjeta en la pantalla de inicio no es seguridad, y por eso aquí se
 * teclean las direcciones a mano y se llaman las acciones sin pasar por la pantalla.
 */
class PermisosTest extends TestCase
{
    use RefreshDatabase;

    /** Rol, dirección y con qué se topa. */
    public static function pantallas(): array
    {
        return [
            // El inicio del vigilante es marcar: al no ver el registro, «/» lo redirige (302) ahí.
            'vigilante · inicio' => [Rol::vigilante(), '/', 302],
            'vigilante · marcar' => [Rol::vigilante(), '/marcar', 200],
            'vigilante · estacionamiento' => [Rol::vigilante(), '/estacionamiento', 200],
            'vigilante · su clave' => [Rol::vigilante(), '/clave', 200],
            'vigilante · registro' => [Rol::vigilante(), '/registro', 403],
            'vigilante · reportes' => [Rol::vigilante(), '/reportes', 403],
            'vigilante · alertas' => [Rol::vigilante(), '/alertas', 403],
            'vigilante · administracion' => [Rol::vigilante(), '/administracion', 403],
            'vigilante · respaldos' => [Rol::vigilante(), '/respaldos', 403],
            'vigilante · asociacion' => [Rol::vigilante(), '/asociacion', 403],
            'vigilante · trabajadores' => [Rol::vigilante(), '/trabajadores', 403],
            'vigilante · organigrama' => [Rol::vigilante(), '/organigrama', 403],
            'vigilante · visitas' => [Rol::vigilante(), '/visitas', 403],
            'vigilante · edificio' => [Rol::vigilante(), '/edificio', 403],
            'vigilante · auditoria' => [Rol::vigilante(), '/auditoria', 403],
            'vigilante · ajustes' => [Rol::vigilante(), '/ajustes', 403],
            'vigilante · usuarios' => [Rol::vigilante(), '/usuarios', 403],
            'vigilante · roles' => [Rol::vigilante(), '/roles', 403],

            'supervisor · inicio' => [Rol::supervisor(), '/', 200],
            'supervisor · marcar' => [Rol::supervisor(), '/marcar', 200],
            'supervisor · su clave' => [Rol::supervisor(), '/clave', 200],
            'supervisor · registro' => [Rol::supervisor(), '/registro', 200],
            'supervisor · reportes' => [Rol::supervisor(), '/reportes', 200],
            'supervisor · alertas' => [Rol::supervisor(), '/alertas', 200],
            'supervisor · administracion' => [Rol::supervisor(), '/administracion', 200],
            'supervisor · respaldos' => [Rol::supervisor(), '/respaldos', 403],
            'supervisor · asociacion' => [Rol::supervisor(), '/asociacion', 403],
            'supervisor · trabajadores' => [Rol::supervisor(), '/trabajadores', 403],
            'supervisor · organigrama' => [Rol::supervisor(), '/organigrama', 403],
            'supervisor · visitas' => [Rol::supervisor(), '/visitas', 200],
            'supervisor · edificio' => [Rol::supervisor(), '/edificio', 403],
            'supervisor · auditoria' => [Rol::supervisor(), '/auditoria', 403],
            'supervisor · ajustes' => [Rol::supervisor(), '/ajustes', 403],
            'supervisor · usuarios' => [Rol::supervisor(), '/usuarios', 200],
            'supervisor · roles' => [Rol::supervisor(), '/roles', 403],

            'administrador · inicio' => [Rol::administrador(), '/', 200],
            'administrador · marcar' => [Rol::administrador(), '/marcar', 200],
            'administrador · estacionamiento' => [Rol::administrador(), '/estacionamiento', 200],
            'administrador · su clave' => [Rol::administrador(), '/clave', 200],
            'administrador · registro' => [Rol::administrador(), '/registro', 200],
            'administrador · reportes' => [Rol::administrador(), '/reportes', 200],
            'administrador · alertas' => [Rol::administrador(), '/alertas', 200],
            'administrador · administracion' => [Rol::administrador(), '/administracion', 200],
            'administrador · respaldos' => [Rol::administrador(), '/respaldos', 200],
            'administrador · asociacion' => [Rol::administrador(), '/asociacion', 200],
            'administrador · trabajadores' => [Rol::administrador(), '/trabajadores', 200],
            'administrador · organigrama' => [Rol::administrador(), '/organigrama', 200],
            'administrador · visitas' => [Rol::administrador(), '/visitas', 200],
            'administrador · edificio' => [Rol::administrador(), '/edificio', 200],
            'administrador · auditoria' => [Rol::administrador(), '/auditoria', 200],
            'administrador · ajustes' => [Rol::administrador(), '/ajustes', 200],
            'administrador · usuarios' => [Rol::administrador(), '/usuarios', 200],
            'administrador · roles' => [Rol::administrador(), '/roles', 200],
        ];
    }

    #[DataProvider('pantallas')]
    public function test_cada_rol_abre_lo_suyo_y_nada_mas(Rol $rol, string $url, int $esperado): void
    {
        $this->actingAs(User::factory()->create(['rol' => $rol]));

        $this->get($url)->assertStatus($esperado);
    }

    /** Rol, permiso y si lo tiene. Es la tabla del README, permiso a permiso. */
    public static function permisos(): array
    {
        return [
            'vigilante · ver-registro' => [Rol::vigilante(), 'ver-registro', false],
            'vigilante · exportar-registro' => [Rol::vigilante(), 'exportar-registro', false],
            'vigilante · gestionar-usuarios' => [Rol::vigilante(), 'gestionar-usuarios', false],
            'vigilante · gestionar-personal' => [Rol::vigilante(), 'gestionar-personal', false],
            'vigilante · gestionar-edificio' => [Rol::vigilante(), 'gestionar-edificio', false],
            'vigilante · gestionar-ajustes' => [Rol::vigilante(), 'gestionar-ajustes', false],
            'vigilante · ver-auditoria' => [Rol::vigilante(), 'ver-auditoria', false],
            'vigilante · gestionar-permisos' => [Rol::vigilante(), 'gestionar-permisos', false],
            'vigilante · ver-foto' => [Rol::vigilante(), 'ver-foto', true],

            'supervisor · ver-registro' => [Rol::supervisor(), 'ver-registro', true],
            'supervisor · exportar-registro' => [Rol::supervisor(), 'exportar-registro', true],
            'supervisor · gestionar-usuarios' => [Rol::supervisor(), 'gestionar-usuarios', true],
            'supervisor · gestionar-personal' => [Rol::supervisor(), 'gestionar-personal', false],
            'supervisor · gestionar-edificio' => [Rol::supervisor(), 'gestionar-edificio', false],
            'supervisor · gestionar-ajustes' => [Rol::supervisor(), 'gestionar-ajustes', false],
            'supervisor · ver-auditoria' => [Rol::supervisor(), 'ver-auditoria', false],
            'supervisor · gestionar-permisos' => [Rol::supervisor(), 'gestionar-permisos', false],
            'supervisor · ver-foto' => [Rol::supervisor(), 'ver-foto', true],

            'administrador · ver-registro' => [Rol::administrador(), 'ver-registro', true],
            'administrador · exportar-registro' => [Rol::administrador(), 'exportar-registro', true],
            'administrador · gestionar-usuarios' => [Rol::administrador(), 'gestionar-usuarios', true],
            'administrador · gestionar-personal' => [Rol::administrador(), 'gestionar-personal', true],
            'administrador · gestionar-edificio' => [Rol::administrador(), 'gestionar-edificio', true],
            'administrador · gestionar-ajustes' => [Rol::administrador(), 'gestionar-ajustes', true],
            'administrador · ver-auditoria' => [Rol::administrador(), 'ver-auditoria', true],
            'administrador · gestionar-permisos' => [Rol::administrador(), 'gestionar-permisos', true],
            'administrador · ver-foto' => [Rol::administrador(), 'ver-foto', true],
        ];
    }

    #[DataProvider('permisos')]
    public function test_los_permisos_son_los_del_readme(Rol $rol, string $permiso, bool $loTiene): void
    {
        $usuario = User::factory()->create(['rol' => $rol]);

        $this->assertSame($loTiene, Gate::forUser($usuario)->allows($permiso));
    }

    /**
     * La prueba que de verdad importa: Livewire manda sus acciones a su propia ruta, no a la de
     * la pantalla. Si el permiso viviera solo en el grupo de rutas, bastaría con hablarle a
     * Livewire directamente para colarse en el registro sin pasar por «rol:supervisor».
     */
    #[Test]
    public function el_vigilante_no_entra_al_registro_ni_hablandole_a_livewire(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        Livewire::test(RegistroDelDia::class)->assertForbidden();
    }

    #[Test]
    public function el_supervisor_si_entra_al_registro_por_livewire(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        Livewire::test(RegistroDelDia::class)->assertOk();
    }

    /**
     * El permiso se revisa en cada acción, no solo al abrir la pantalla.
     *
     * «mount» corre una sola vez; las acciones posteriores rehidratan el componente sin volver a
     * montarlo. Si el permiso viviera en «mount», a quien le quitaran el rol con la pantalla ya
     * abierta le seguiría funcionando todo hasta que la recargara.
     */
    #[Test]
    public function al_que_le_bajan_el_rol_con_la_pantalla_abierta_deja_de_funcionarle(): void
    {
        $usuario = User::factory()->supervisor()->create();
        $this->actingAs($usuario);

        $componente = Livewire::test(RegistroDelDia::class)->assertOk();

        $usuario->update(['rol' => Rol::vigilante()]);

        $componente->call('verHoy')->assertForbidden();
    }

    /**
     * Exportar tiene su propio permiso, aparte de ver: sacar el día entero a un archivo que se
     * lleva en un pendrive no es lo mismo que mirarlo en pantalla. Hoy los dos caen en el mismo
     * rol, pero están separados para que cambiar uno no arrastre al otro.
     */
    #[Test]
    public function exportar_pide_su_propio_permiso(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        Livewire::test(RegistroDelDia::class)
            ->call('exportar')
            ->assertOk();

        $this->assertFalse(
            Gate::forUser(User::factory()->create(['rol' => Rol::vigilante()]))->allows('exportar-registro'),
        );
    }

    /** La foto la ven los tres: el vigilante la necesita para comprobar quién tiene delante. */
    #[Test]
    public function la_foto_la_ve_tambien_el_vigilante(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        $persona = Persona::create([
            'cedula' => '12345678',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'Ana Rodríguez Peña',
            'dependencia' => 'Recursos Humanos',
            'activo' => true,
        ]);

        // Sin foto da 404, no 403: el permiso pasó, lo que no hay es archivo.
        $this->get(route('persona.foto', $persona))->assertNotFound();
    }
}
