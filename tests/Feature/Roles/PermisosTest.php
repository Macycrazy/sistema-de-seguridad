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
            'vigilante · inicio' => [Rol::VIGILANTE, '/', 302],
            'vigilante · marcar' => [Rol::VIGILANTE, '/marcar', 200],
            'vigilante · su clave' => [Rol::VIGILANTE, '/clave', 200],
            'vigilante · registro' => [Rol::VIGILANTE, '/registro', 403],
            'vigilante · reportes' => [Rol::VIGILANTE, '/reportes', 403],
            'vigilante · alertas' => [Rol::VIGILANTE, '/alertas', 403],
            'vigilante · trabajadores' => [Rol::VIGILANTE, '/trabajadores', 403],
            'vigilante · edificio' => [Rol::VIGILANTE, '/edificio', 403],
            'vigilante · auditoria' => [Rol::VIGILANTE, '/auditoria', 403],
            'vigilante · ajustes' => [Rol::VIGILANTE, '/ajustes', 403],
            'vigilante · usuarios' => [Rol::VIGILANTE, '/usuarios', 403],
            'vigilante · roles' => [Rol::VIGILANTE, '/roles', 403],

            'supervisor · inicio' => [Rol::SUPERVISOR, '/', 200],
            'supervisor · marcar' => [Rol::SUPERVISOR, '/marcar', 200],
            'supervisor · su clave' => [Rol::SUPERVISOR, '/clave', 200],
            'supervisor · registro' => [Rol::SUPERVISOR, '/registro', 200],
            'supervisor · reportes' => [Rol::SUPERVISOR, '/reportes', 200],
            'supervisor · alertas' => [Rol::SUPERVISOR, '/alertas', 200],
            'supervisor · trabajadores' => [Rol::SUPERVISOR, '/trabajadores', 403],
            'supervisor · edificio' => [Rol::SUPERVISOR, '/edificio', 403],
            'supervisor · auditoria' => [Rol::SUPERVISOR, '/auditoria', 403],
            'supervisor · ajustes' => [Rol::SUPERVISOR, '/ajustes', 403],
            'supervisor · usuarios' => [Rol::SUPERVISOR, '/usuarios', 200],
            'supervisor · roles' => [Rol::SUPERVISOR, '/roles', 403],

            'administrador · inicio' => [Rol::ADMINISTRADOR, '/', 200],
            'administrador · marcar' => [Rol::ADMINISTRADOR, '/marcar', 200],
            'administrador · su clave' => [Rol::ADMINISTRADOR, '/clave', 200],
            'administrador · registro' => [Rol::ADMINISTRADOR, '/registro', 200],
            'administrador · reportes' => [Rol::ADMINISTRADOR, '/reportes', 200],
            'administrador · alertas' => [Rol::ADMINISTRADOR, '/alertas', 200],
            'administrador · trabajadores' => [Rol::ADMINISTRADOR, '/trabajadores', 200],
            'administrador · edificio' => [Rol::ADMINISTRADOR, '/edificio', 200],
            'administrador · auditoria' => [Rol::ADMINISTRADOR, '/auditoria', 200],
            'administrador · ajustes' => [Rol::ADMINISTRADOR, '/ajustes', 200],
            'administrador · usuarios' => [Rol::ADMINISTRADOR, '/usuarios', 200],
            'administrador · roles' => [Rol::ADMINISTRADOR, '/roles', 200],
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
            'vigilante · ver-registro' => [Rol::VIGILANTE, 'ver-registro', false],
            'vigilante · exportar-registro' => [Rol::VIGILANTE, 'exportar-registro', false],
            'vigilante · gestionar-usuarios' => [Rol::VIGILANTE, 'gestionar-usuarios', false],
            'vigilante · gestionar-personal' => [Rol::VIGILANTE, 'gestionar-personal', false],
            'vigilante · gestionar-edificio' => [Rol::VIGILANTE, 'gestionar-edificio', false],
            'vigilante · gestionar-ajustes' => [Rol::VIGILANTE, 'gestionar-ajustes', false],
            'vigilante · ver-auditoria' => [Rol::VIGILANTE, 'ver-auditoria', false],
            'vigilante · gestionar-permisos' => [Rol::VIGILANTE, 'gestionar-permisos', false],
            'vigilante · ver-foto' => [Rol::VIGILANTE, 'ver-foto', true],

            'supervisor · ver-registro' => [Rol::SUPERVISOR, 'ver-registro', true],
            'supervisor · exportar-registro' => [Rol::SUPERVISOR, 'exportar-registro', true],
            'supervisor · gestionar-usuarios' => [Rol::SUPERVISOR, 'gestionar-usuarios', true],
            'supervisor · gestionar-personal' => [Rol::SUPERVISOR, 'gestionar-personal', false],
            'supervisor · gestionar-edificio' => [Rol::SUPERVISOR, 'gestionar-edificio', false],
            'supervisor · gestionar-ajustes' => [Rol::SUPERVISOR, 'gestionar-ajustes', false],
            'supervisor · ver-auditoria' => [Rol::SUPERVISOR, 'ver-auditoria', false],
            'supervisor · gestionar-permisos' => [Rol::SUPERVISOR, 'gestionar-permisos', false],
            'supervisor · ver-foto' => [Rol::SUPERVISOR, 'ver-foto', true],

            'administrador · ver-registro' => [Rol::ADMINISTRADOR, 'ver-registro', true],
            'administrador · exportar-registro' => [Rol::ADMINISTRADOR, 'exportar-registro', true],
            'administrador · gestionar-usuarios' => [Rol::ADMINISTRADOR, 'gestionar-usuarios', true],
            'administrador · gestionar-personal' => [Rol::ADMINISTRADOR, 'gestionar-personal', true],
            'administrador · gestionar-edificio' => [Rol::ADMINISTRADOR, 'gestionar-edificio', true],
            'administrador · gestionar-ajustes' => [Rol::ADMINISTRADOR, 'gestionar-ajustes', true],
            'administrador · ver-auditoria' => [Rol::ADMINISTRADOR, 'ver-auditoria', true],
            'administrador · gestionar-permisos' => [Rol::ADMINISTRADOR, 'gestionar-permisos', true],
            'administrador · ver-foto' => [Rol::ADMINISTRADOR, 'ver-foto', true],
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
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));

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

        $usuario->update(['rol' => Rol::VIGILANTE]);

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
            Gate::forUser(User::factory()->create(['rol' => Rol::VIGILANTE]))->allows('exportar-registro'),
        );
    }

    /** La foto la ven los tres: el vigilante la necesita para comprobar quién tiene delante. */
    #[Test]
    public function la_foto_la_ve_tambien_el_vigilante(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));

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
