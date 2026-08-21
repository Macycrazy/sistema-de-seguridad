<?php

namespace Tests\Feature\Administracion;

use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PanelAdministracionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_vigilante_no_ve_el_panel(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
        $this->get(route('administracion'))->assertForbidden();
    }

    #[Test]
    public function el_administrador_ve_todas_las_tarjetas(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $this->get(route('administracion'))
            ->assertOk()
            ->assertSee('Trabajadores')
            ->assertSee('Organigrama')
            ->assertSee('Usuarios')
            ->assertSee('Edificio')
            ->assertSee('Ajustes')
            ->assertSee('Auditoría')
            ->assertSee('Roles');
    }

    #[Test]
    public function el_supervisor_entra_pero_solo_ve_lo_suyo(): void
    {
        // El supervisor tiene «gestionar-usuarios», así que el panel se abre, pero solo con su
        // tarjeta: nada de Ajustes, Roles ni Auditoría.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $this->get(route('administracion'))
            ->assertOk()
            ->assertSee('Usuarios')
            ->assertDontSee('Roles')
            ->assertDontSee('Auditoría');
    }
}
