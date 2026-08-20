<?php

namespace Tests\Feature\Estacionamiento;

use App\Livewire\Estacionamiento\ListaDePuestos;
use App\Models\Puesto;
use App\Models\User;
use App\Services\DatosVehiculo;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaPuestosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_quien_administra_el_edificio_abre_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->get(route('puestos'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $this->get(route('puestos'))->assertOk();
    }

    #[Test]
    public function agregar_un_puesto_lo_deja_en_el_catalogo(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(ListaDePuestos::class)
            ->set('codigo', 's2-14')
            ->set('tipo', DatosVehiculo::CARRO)
            ->set('zona', 'Sótano 2')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('puestos', ['codigo' => 'S2-14', 'tipo' => 'carro', 'zona' => 'Sótano 2']);
    }

    #[Test]
    public function editar_carga_y_cambia_los_datos(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $puesto = Puesto::create(['codigo' => 'A-1', 'tipo' => 'carro', 'orden' => 1]);

        Livewire::test(ListaDePuestos::class)
            ->call('editar', $puesto->id)
            ->assertSet('codigo', 'A-1')
            ->set('tipo', DatosVehiculo::MOTO)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('moto', $puesto->fresh()->tipo);
    }

    #[Test]
    public function deshabilitar_y_quitar_un_puesto(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);

        Livewire::test(ListaDePuestos::class)
            ->call('activar', $puesto->id, false);
        $this->assertFalse($puesto->fresh()->activo);

        Livewire::test(ListaDePuestos::class)
            ->call('eliminar', $puesto->id);
        $this->assertDatabaseMissing('puestos', ['codigo' => 'A-1']);
    }
}
