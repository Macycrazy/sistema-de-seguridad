<?php

namespace Tests\Feature\Trabajadores;

use App\Livewire\Trabajadores\ListaDeTrabajadores;
use App\Models\Persona;
use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaTrabajadoresTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_quien_tiene_el_permiso_abre_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->get(route('trabajadores'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $this->get(route('trabajadores'))->assertOk();
    }

    #[Test]
    public function el_alta_manual_crea_un_trabajador(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(ListaDeTrabajadores::class)
            ->set('cedula', '12.345.678')
            ->set('nombre', 'Ana Pérez')
            ->set('ente', 'ciip')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', [
            'cedula' => '12345678',
            'nombre' => 'ANA PÉREZ',
            'tipo' => 'trabajador',
        ]);
    }

    #[Test]
    public function el_alta_con_datos_malos_muestra_el_error_y_no_crea_nada(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(ListaDeTrabajadores::class)
            ->set('cedula', '123')
            ->set('nombre', 'Ana')
            ->call('guardar')
            ->assertHasErrors('cedula');

        $this->assertDatabaseCount('personas', 0);
    }

    #[Test]
    public function la_lista_encuentra_por_nombre_y_no_muestra_invitados(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Persona::create(['cedula' => '12345678', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA PÉREZ', 'activo' => true]);
        Persona::create(['cedula' => '99887766', 'tipo' => Persona::INVITADO, 'nombre' => 'PEDRO VISITA', 'activo' => true]);

        Livewire::test(ListaDeTrabajadores::class)
            ->set('busqueda', 'ana')
            ->assertSee('ANA PÉREZ')
            ->set('busqueda', 'pedro')
            ->assertDontSee('PEDRO VISITA');
    }
}
