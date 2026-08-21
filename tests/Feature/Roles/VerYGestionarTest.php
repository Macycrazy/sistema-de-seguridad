<?php

namespace Tests\Feature\Roles;

use App\Livewire\Trabajadores\ListaDeTrabajadores;
use App\Models\User;
use App\Services\Permisos;
use App\Usuarios\Permiso;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El permiso de cada módulo se parte en «ver» (entrar y mirar) y «gestionar» (cambiar), y gestionar
 * implica ver. Se prueba sobre el personal, que es representativo del resto.
 */
class VerYGestionarTest extends TestCase
{
    use RefreshDatabase;

    private function permisos(): Permisos
    {
        return app(Permisos::class);
    }

    /** Deja a un rol EXACTAMENTE con los permisos que se le pasen. */
    private function soloConEstos(Rol $rol, Permiso ...$permisos): void
    {
        $admin = User::factory()->create(['rol' => Rol::ADMINISTRADOR]);
        $this->permisos()->guardar($rol, $permisos, $admin);
    }

    #[Test]
    public function gestionar_implica_ver(): void
    {
        $this->soloConEstos(Rol::SUPERVISOR, Permiso::GESTIONAR_PERSONAL);

        // No se le marcó «ver-personal», pero al poder gestionar, puede ver.
        $this->assertTrue($this->permisos()->tiene(Rol::SUPERVISOR, Permiso::VER_PERSONAL));
    }

    #[Test]
    public function con_solo_ver_entra_a_la_pantalla(): void
    {
        $this->soloConEstos(Rol::SUPERVISOR, Permiso::VER_PERSONAL);

        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));

        $this->get(route('trabajadores'))->assertOk();
    }

    #[Test]
    public function con_solo_ver_no_puede_gestionar(): void
    {
        $this->soloConEstos(Rol::SUPERVISOR, Permiso::VER_PERSONAL);

        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));

        Livewire::test(ListaDeTrabajadores::class)
            ->set('cedula', '12.345.678')
            ->set('nombre', 'Ana Pérez')
            ->set('ente', 'ciip')
            ->call('guardar')
            ->assertForbidden();

        $this->assertDatabaseCount('personas', 0);
    }

    #[Test]
    public function sin_ver_no_entra(): void
    {
        $this->soloConEstos(Rol::SUPERVISOR); // sin ningún permiso de personal

        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));

        $this->get(route('trabajadores'))->assertForbidden();
    }

    #[Test]
    public function con_gestionar_entra_y_cambia(): void
    {
        $this->soloConEstos(Rol::SUPERVISOR, Permiso::GESTIONAR_PERSONAL);

        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));

        $this->get(route('trabajadores'))->assertOk();

        Livewire::test(ListaDeTrabajadores::class)
            ->set('cedula', '12.345.678')
            ->set('nombre', 'Ana Pérez')
            ->set('ente', 'ciip')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', ['cedula' => '12345678']);
    }
}
