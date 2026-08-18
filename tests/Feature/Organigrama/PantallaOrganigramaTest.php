<?php

namespace Tests\Feature\Organigrama;

use App\Livewire\Organigrama\Arbol;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaOrganigramaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_quien_gestiona_personal_entra(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));
        $this->get(route('organigrama'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $this->get(route('organigrama'))->assertOk();
    }

    #[Test]
    public function crear_una_unidad_la_lista_y_deja_rastro(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(Arbol::class)
            ->call('abrirAlta')
            ->set('nombre', 'GERENCIA DE LITIGIOS')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('GERENCIA DE LITIGIOS');

        $this->assertDatabaseHas('departamentos', ['nombre' => 'GERENCIA DE LITIGIOS']);
        $this->assertDatabaseHas('bitacora', ['accion' => Auditoria::CAMBIO_ORGANIGRAMA]);
    }

    #[Test]
    public function un_nombre_vacio_no_crea_nada_y_pinta_el_error(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(Arbol::class)
            ->call('abrirAlta')
            ->set('nombre', '')
            ->call('guardar')
            ->assertHasErrors('nombre');

        $this->assertSame(0, Departamento::count());
    }
}
