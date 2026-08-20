<?php

namespace Tests\Feature\Estacionamiento;

use App\Livewire\Estacionamiento\Panel;
use App\Models\Puesto;
use App\Models\User;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use App\Usuarios\Rol;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaEstacionamientoTest extends TestCase
{
    use RefreshDatabase;

    private function estadia(string $placa, array $extra = []): VehiculoFijo
    {
        return VehiculoFijo::create(array_merge([
            'placa' => $placa,
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'entro_en' => CarbonImmutable::now()->subHour(),
        ], $extra));
    }

    #[Test]
    public function el_guardia_la_ve_sin_permiso_especial(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->get(route('estacionamiento'))->assertOk();
    }

    #[Test]
    public function lista_un_vehiculo_dentro_con_su_placa_y_conductor(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->estadia('AB123CD', ['conductor_nombre' => 'ANA PÉREZ']);

        Livewire::test(Panel::class)
            ->assertOk()
            ->assertSee('AB123CD')
            ->assertSee('ANA PÉREZ');
    }

    #[Test]
    public function desde_el_panel_se_le_asigna_el_puesto_a_un_vehiculo_dentro(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $estadia = $this->estadia('AB123CD');

        Livewire::test(Panel::class)
            ->call('asignarPuesto', $estadia->id, (string) $puesto->id);

        $this->assertSame($puesto->id, $estadia->fresh()->puesto_id);
    }

    #[Test]
    public function el_panel_avisa_de_los_que_pernoctan(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->estadia('AB123CD', ['entro_en' => CarbonImmutable::yesterday()->setTime(21, 0)]);

        Livewire::test(Panel::class)
            ->assertOk()
            ->assertSee('Pernoctan')
            ->assertSee('AB123CD');
    }

    #[Test]
    public function buscar_por_placa_deja_solo_la_que_coincide(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->estadia('ABC123');
        $this->estadia('XYZ789');

        Livewire::test(Panel::class)
            ->set('busqueda', 'abc')
            ->assertSee('ABC123')
            ->assertDontSee('XYZ789');
    }
}
