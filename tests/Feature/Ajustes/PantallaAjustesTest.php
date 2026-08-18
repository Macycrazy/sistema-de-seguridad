<?php

namespace Tests\Feature\Ajustes;

use App\Livewire\Ajustes\ListaDeTiempos;
use App\Models\Parametro;
use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaAjustesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_quien_tiene_el_permiso_abre_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));
        $this->get(route('ajustes'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $this->get(route('ajustes'))->assertOk();
    }

    #[Test]
    public function guardar_ajusta_los_plazos(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(ListaDeTiempos::class)
            ->set('valores.minutos_entre_salida_y_entrada', 25)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(25, Parametro::where('clave', 'minutos_entre_salida_y_entrada')->first()->valor);
    }

    #[Test]
    public function un_valor_absurdo_muestra_el_error_y_no_lo_guarda(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(ListaDeTiempos::class)
            ->set('valores.segundos_antiduplicado', 99999)
            ->call('guardar')
            ->assertHasErrors('valores.segundos_antiduplicado');

        $this->assertNotSame(99999, Parametro::where('clave', 'segundos_antiduplicado')->first()->valor);
    }
}
