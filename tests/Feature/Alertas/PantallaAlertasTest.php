<?php

namespace Tests\Feature\Alertas;

use App\Livewire\Alertas\Panel;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\User;
use App\Usuarios\Rol;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaAlertasTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sin_ver_registro_no_se_entra(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->get(route('alertas'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));
        $this->get(route('alertas'))->assertOk();
    }

    #[Test]
    public function el_panel_lista_una_permanencia_larga(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));
        $ana = Persona::create(['cedula' => '1', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA PÉREZ', 'activo' => true]);
        Movimiento::create(['persona_id' => $ana->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => CarbonImmutable::now()->subHours(13)]);

        Livewire::test(Panel::class)
            ->assertOk()
            ->assertSee('ANA PÉREZ')
            ->assertSee('AVISO');   // la etiqueta de gravedad, en mayúscula como todas
    }

    #[Test]
    public function sin_alertas_lo_dice(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));

        Livewire::test(Panel::class)
            ->assertOk()
            ->assertSee('Nada que reportar');
    }
}
