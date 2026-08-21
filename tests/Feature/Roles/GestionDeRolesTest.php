<?php

namespace Tests\Feature\Roles;

use App\Livewire\Roles\PermisosPorRol;
use App\Models\User;
use App\Services\GestionDeRoles;
use App\Services\Permisos;
use App\Usuarios\Permiso;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Crear más roles desde la pantalla. Los tres base siguen fijos; los nuevos se anclan a un nivel y
 * heredan de él la jerarquía (a quién pueden tocar), sin poder pasar del administrador.
 */
class GestionDeRolesTest extends TestCase
{
    use RefreshDatabase;

    private function servicio(): GestionDeRoles
    {
        return app(GestionDeRoles::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->create();
    }

    #[Test]
    public function crear_un_rol_lo_agrega_con_su_nivel(): void
    {
        $rol = $this->servicio()->crear('Recepción', 2, $this->admin());

        $this->assertSame('recepcion', $rol->value);
        $this->assertSame('Recepción', $rol->nombre);
        $this->assertSame(2, $rol->nivel);
        $this->assertFalse($rol->esBase());

        Rol::olvidar();
        $slugs = array_column(Rol::cases(), 'value');
        $this->assertContains('recepcion', $slugs);
    }

    #[Test]
    public function el_rol_nuevo_hereda_la_jerarquia_de_su_nivel(): void
    {
        $recepcion = $this->servicio()->crear('Recepción', 2, $this->admin());

        // Nivel 2: alcanza como el supervisor —vigilante sí, administrador no—.
        $this->assertTrue($recepcion->alcanza(Rol::vigilante()));
        $this->assertTrue($recepcion->alcanza(Rol::supervisor()));
        $this->assertFalse($recepcion->alcanza(Rol::administrador()));

        // Y un administrador lo alcanza a él.
        $this->assertTrue(Rol::administrador()->alcanza($recepcion));
    }

    #[Test]
    public function un_usuario_puede_llevar_el_rol_nuevo(): void
    {
        $this->servicio()->crear('Recepción', 2, $this->admin());

        $usuario = User::factory()->create(['rol' => 'recepcion']);

        $this->assertSame('recepcion', $usuario->rol->value);
        $this->assertTrue($usuario->alcanza(Rol::vigilante()));
    }

    #[Test]
    public function no_deja_dos_roles_con_el_mismo_nombre(): void
    {
        $this->servicio()->crear('Recepción', 2, $this->admin());

        $this->expectException(ValidationException::class);
        $this->servicio()->crear('recepción', 1, $this->admin());
    }

    #[Test]
    public function el_nivel_no_puede_pasar_del_administrador(): void
    {
        $this->expectException(ValidationException::class);
        $this->servicio()->crear('Súper', 4, $this->admin());
    }

    #[Test]
    public function los_roles_base_no_se_editan_ni_se_borran(): void
    {
        $admin = $this->admin();

        try {
            $this->servicio()->eliminar(Rol::supervisor(), $admin);
            $this->fail('Se pudo borrar un rol base.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $this->servicio()->editar(Rol::administrador(), 'Jefe', 3, $admin);
    }

    #[Test]
    public function no_se_borra_un_rol_que_alguien_usa(): void
    {
        $rol = $this->servicio()->crear('Recepción', 2, $this->admin());
        User::factory()->create(['rol' => $rol->value]);

        $this->expectException(ValidationException::class);
        $this->servicio()->eliminar($rol, $this->admin());
    }

    #[Test]
    public function borrar_un_rol_libre_se_lleva_sus_permisos(): void
    {
        $admin = $this->admin();
        $rol = $this->servicio()->crear('Recepción', 2, $admin);

        app(Permisos::class)->guardar($rol, [Permiso::VER_REGISTRO], $admin);
        $this->assertDatabaseHas('permisos_de_rol', ['rol' => 'recepcion', 'permiso' => 'ver-registro']);

        $this->servicio()->eliminar($rol, $admin);

        $this->assertDatabaseMissing('permisos_de_rol', ['rol' => 'recepcion']);
        $this->assertDatabaseMissing('roles', ['slug' => 'recepcion']);
    }

    #[Test]
    public function un_rol_nuevo_nunca_recibe_gestionar_permisos(): void
    {
        $admin = $this->admin();
        $rol = $this->servicio()->crear('Recepción', 3, $admin);

        // Aunque se pida explícitamente, «gestionar-permisos» es intocable: se filtra.
        app(Permisos::class)->guardar($rol, [Permiso::GESTIONAR_PERMISOS], $admin);

        $this->assertFalse(app(Permisos::class)->tiene($rol, Permiso::GESTIONAR_PERMISOS));
    }

    #[Test]
    public function la_pantalla_crea_un_rol(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PermisosPorRol::class)
            ->call('abrirNuevoRol')
            ->set('nombreRol', 'Auditor')
            ->set('nivelRol', '1')
            ->call('guardarRol')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('roles', ['slug' => 'auditor', 'nivel' => 1, 'base' => false]);
    }

    #[Test]
    public function la_pantalla_solo_la_abre_quien_gestiona_permisos(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        Livewire::test(PermisosPorRol::class)->assertForbidden();
    }
}
