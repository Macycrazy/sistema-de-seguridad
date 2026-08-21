<?php

namespace Tests\Feature\Roles;

use App\Livewire\Roles\PermisosPorRol;
use App\Livewire\Usuarios\ListaDeUsuarios;
use App\Models\User;
use App\Services\Permisos;
use App\Usuarios\Permiso;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La pantalla de roles: qué puede hacer cada uno.
 *
 * Lo que más importa aquí no es que se guarde, sino que no se pueda usar para dejar el sistema sin
 * salida ni para ascenderse. Dos cosas la sostienen:
 *
 * 1. «Gestionar permisos» está clavado en administrador: ni se le quita ni se le da a otro.
 * 2. El orden de los roles no se toca desde aquí. Un vigilante con «gestionar usuarios» crea
 *    vigilantes, no administradores.
 */
class GestionDePermisosTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        $administrador = User::factory()->administrador()->create();

        $this->actingAs($administrador);

        return $administrador;
    }

    private function permisos(): Permisos
    {
        return app(Permisos::class);
    }

    #[Test]
    public function de_fabrica_los_permisos_son_los_del_readme(): void
    {
        foreach (Permiso::cases() as $permiso) {
            foreach (Rol::cases() as $rol) {
                $this->assertSame(
                    in_array($rol, $permiso->porOmision(), true),
                    $this->permisos()->tiene($rol, $permiso),
                    "«{$permiso->value}» para «{$rol->value}» no arrancó como dice el enum.",
                );
            }
        }
    }

    #[Test]
    public function quitarle_un_permiso_a_un_rol_le_cierra_la_pantalla_de_verdad(): void
    {
        $this->administrador();

        Livewire::test(PermisosPorRol::class)
            ->set('matriz.supervisor.ver-registro', false)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->actingAs(User::factory()->supervisor()->create());

        $this->get('/registro')->assertForbidden();
    }

    #[Test]
    public function darle_un_permiso_a_un_rol_le_abre_la_pantalla_de_verdad(): void
    {
        $this->administrador();

        Livewire::test(PermisosPorRol::class)
            ->set('matriz.vigilante.ver-registro', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->actingAs(User::factory()->create());

        $this->get('/registro')->assertOk();
    }

    /**
     * Quitarle «gestionar permisos» al administrador cerraría esta pantalla para siempre, y el
     * arreglo sería entrar a la base a mano. No se puede, ni marcando la casilla —que ni se
     * dibuja— ni mandándolo por Livewire.
     */
    #[Test]
    public function al_administrador_no_se_le_quita_gestionar_permisos(): void
    {
        $administrador = $this->administrador();

        Livewire::test(PermisosPorRol::class)
            ->set('matriz.administrador.gestionar-permisos', false)
            ->call('guardar');

        $this->assertTrue($this->permisos()->tiene(Rol::administrador(), Permiso::GESTIONAR_PERMISOS));

        $this->actingAs($administrador->fresh());
        $this->get('/roles')->assertOk();
    }

    /** Dárselo a otro rol sería dejarle concederse todo lo demás en dos clics. */
    #[Test]
    public function gestionar_permisos_no_se_le_da_a_ningun_otro_rol(): void
    {
        $this->administrador();

        Livewire::test(PermisosPorRol::class)
            ->set('matriz.supervisor.gestionar-permisos', true)
            ->set('matriz.vigilante.gestionar-permisos', true)
            ->call('guardar');

        $this->assertFalse($this->permisos()->tiene(Rol::supervisor(), Permiso::GESTIONAR_PERMISOS));
        $this->assertFalse($this->permisos()->tiene(Rol::vigilante(), Permiso::GESTIONAR_PERMISOS));

        $this->actingAs(User::factory()->supervisor()->create());
        $this->get('/roles')->assertForbidden();
    }

    /**
     * La prueba que sostiene que esta pantalla se pueda tener abierta sin miedo: los permisos
     * dicen a qué pantallas llega un rol, NO a quién puede tocar. Aunque se le dé «gestionar
     * usuarios» al vigilante, sigue sin poder crear un administrador.
     */
    #[Test]
    public function los_permisos_no_mueven_el_orden_de_los_roles(): void
    {
        $this->administrador();

        Livewire::test(PermisosPorRol::class)
            ->set('matriz.vigilante.gestionar-usuarios', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->actingAs(User::factory()->create());

        // Entra a la pantalla, porque el permiso se lo dieron...
        Livewire::test(ListaDeUsuarios::class)->assertOk();

        // ...pero no se crea un administrador, porque eso no es un permiso.
        Livewire::test(ListaDeUsuarios::class)
            ->set('usuario', 'colado')
            ->set('nombre', 'El Colado')
            ->set('rol', Rol::administrador()->value)
            ->set('clave', 'la-que-yo-quiera')
            ->call('crear')
            ->assertHasErrors('rol');

        $this->assertDatabaseMissing('users', ['usuario' => 'colado']);
    }

    #[Test]
    public function restablecer_devuelve_todo_a_como_venia(): void
    {
        $this->administrador();

        Livewire::test(PermisosPorRol::class)
            ->set('matriz.supervisor.ver-registro', false)
            ->set('matriz.vigilante.ver-auditoria', true)
            ->call('guardar')
            ->call('restablecer')
            ->assertHasNoErrors();

        $this->assertTrue($this->permisos()->tiene(Rol::supervisor(), Permiso::VER_REGISTRO));
        $this->assertFalse($this->permisos()->tiene(Rol::vigilante(), Permiso::VER_AUDITORIA));
    }

    /** Por Livewire puede llegar cualquier clave en la matriz; a la base solo entran las del enum. */
    #[Test]
    public function un_permiso_inventado_no_entra_en_la_base(): void
    {
        $this->administrador();

        Livewire::test(PermisosPorRol::class)
            ->set('matriz.vigilante.hacer-lo-que-sea', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('permisos_de_rol', ['permiso' => 'hacer-lo-que-sea']);
    }

    #[Test]
    public function el_servicio_no_le_deja_guardar_a_quien_no_es_administrador(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->expectException(ValidationException::class);

        $this->permisos()->guardar(Rol::vigilante(), Permiso::cases(), $supervisor);
    }
}
