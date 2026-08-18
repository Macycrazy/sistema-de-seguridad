<?php

namespace Tests\Feature\Estacionamiento;

use App\Livewire\Estacionamiento\Panel;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\User;
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

    #[Test]
    public function el_guardia_la_ve_sin_permiso_especial(): void
    {
        // El vigilante —el guardia— entra: es su pantalla, como la de marcar.
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->get(route('estacionamiento'))->assertOk();
    }

    #[Test]
    public function lista_un_vehiculo_dentro_con_su_placa(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $ana = Persona::create(['cedula' => '12345678', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA PÉREZ', 'activo' => true]);
        Movimiento::create([
            'persona_id' => $ana->id,
            'tipo' => Movimiento::ENTRADA,
            'ocurrio_en' => CarbonImmutable::now()->subHour(),
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'placa' => 'AB123CD',
        ]);

        Livewire::test(Panel::class)
            ->assertOk()
            ->assertSee('AB123CD')
            ->assertSee('ANA PÉREZ');
    }
}
