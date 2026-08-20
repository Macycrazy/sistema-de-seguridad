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

    #[Test]
    public function editar_un_trabajador_actualiza_sus_datos_y_deja_la_cedula_fija(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $t = Persona::create([
            'cedula' => '12345678', 'tipo' => Persona::TRABAJADOR,
            'nombre' => 'ANA', 'dependencia' => 'VIEJA', 'activo' => true,
        ]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('editar', $t->id)
            ->assertSet('creando', true)
            ->assertSet('editandoId', $t->id)
            ->assertSet('cedula', '12345678')
            ->set('nombre', 'Ana María')
            ->set('dependencia', 'Tecnología')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', [
            'id' => $t->id, 'cedula' => '12345678',
            'nombre' => 'ANA MARÍA', 'dependencia' => 'TECNOLOGÍA', 'tipo' => 'trabajador',
        ]);
    }

    #[Test]
    public function editar_un_invitado_corrige_sus_datos_y_sigue_siendo_invitado(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $inv = Persona::create([
            'cedula' => '99887766', 'tipo' => Persona::INVITADO,
            'nombre' => 'PEDRO', 'motivo' => 'reunion', 'activo' => true,
        ]);

        Livewire::test(ListaDeTrabajadores::class)
            ->set('filtro', Persona::INVITADO)
            ->call('editar', $inv->id)
            ->assertSet('motivo', 'reunion')
            ->set('nombre', 'Pedro Pérez')
            ->set('motivo', 'entrega de equipos')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', [
            'id' => $inv->id, 'tipo' => 'invitado',
            'nombre' => 'PEDRO PÉREZ', 'motivo' => 'entrega de equipos',
        ]);
    }

    #[Test]
    public function filtra_por_gerencia_ente_y_estado(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        Persona::create(['cedula' => '11111111', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA TECNO', 'dependencia' => 'TECNOLOGÍA', 'ente' => Persona::ENTE_CIIP, 'activo' => true]);
        Persona::create(['cedula' => '22222222', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'LUIS HUMANO', 'dependencia' => 'GESTIÓN HUMANA', 'ente' => Persona::ENTE_CIIP, 'activo' => true]);
        Persona::create(['cedula' => '33333333', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ROSA MARCA', 'dependencia' => 'TECNOLOGÍA', 'ente' => Persona::ENTE_MARCA_PAIS, 'activo' => false]);

        // Por gerencia: solo Tecnología (Ana y Rosa), no la de Gestión Humana.
        Livewire::test(ListaDeTrabajadores::class)
            ->set('filtroGerencia', 'TECNOLOGÍA')
            ->assertSee('ANA TECNO')
            ->assertSee('ROSA MARCA')
            ->assertDontSee('LUIS HUMANO')
            // Sumando ente CIIP: se cae Rosa (Marca País).
            ->set('filtroEnte', Persona::ENTE_CIIP)
            ->assertSee('ANA TECNO')
            ->assertDontSee('ROSA MARCA');

        // Por estado inactivo: solo Rosa.
        Livewire::test(ListaDeTrabajadores::class)
            ->set('filtroEstado', 'inactivo')
            ->assertSee('ROSA MARCA')
            ->assertDontSee('ANA TECNO');
    }

    #[Test]
    public function el_filtro_de_invitados_muestra_solo_las_visitas(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        Persona::create(['cedula' => '12345678', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA TRABAJA', 'activo' => true]);
        Persona::create(['cedula' => '99887766', 'tipo' => Persona::INVITADO, 'nombre' => 'PEDRO VISITA', 'activo' => true]);

        Livewire::test(ListaDeTrabajadores::class)
            ->set('filtro', Persona::INVITADO)
            ->assertSee('PEDRO VISITA')
            ->assertDontSee('ANA TRABAJA');
    }
}
