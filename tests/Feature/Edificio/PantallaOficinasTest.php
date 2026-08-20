<?php

namespace Tests\Feature\Edificio;

use App\Livewire\Edificio\ListaDeOficinas;
use App\Models\Oficina;
use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaOficinasTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_quien_tiene_el_permiso_abre_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->get(route('edificio'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $this->get(route('edificio'))->assertOk();
    }

    #[Test]
    public function agregar_una_oficina_la_deja_en_el_catalogo(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(ListaDeOficinas::class)
            ->set('codigo', '6-1')
            ->set('nombre', 'Sala nueva')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('oficinas', ['codigo' => '6-1', 'nombre' => 'Sala nueva']);
    }

    #[Test]
    public function asocia_una_gerencia_al_piso_en_mayusculas(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(ListaDeOficinas::class)
            ->set('codigo', '4-1')
            ->set('nombre', 'Sala')
            ->set('gerencia', 'Gestión Humana')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('oficinas', ['codigo' => '4-1', 'gerencia' => 'GESTIÓN HUMANA']);
    }

    #[Test]
    public function agregar_sin_codigo_muestra_el_error(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(ListaDeOficinas::class)
            ->set('codigo', '')
            ->call('guardar')
            ->assertHasErrors('codigo');
    }

    #[Test]
    public function quitar_una_oficina_la_borra(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $oficina = Oficina::create(['codigo' => 'X-1', 'orden' => 99]);

        Livewire::test(ListaDeOficinas::class)
            ->call('eliminar', $oficina->id);

        $this->assertDatabaseMissing('oficinas', ['codigo' => 'X-1']);
    }
}
