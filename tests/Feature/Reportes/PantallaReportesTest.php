<?php

namespace Tests\Feature\Reportes;

use App\Livewire\Reportes\Panel;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\User;
use App\Services\Reportes\Reportes;
use App\Usuarios\Rol;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaReportesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sin_ver_registro_no_se_entra(): void
    {
        // El vigilante no tiene «ver-registro».
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $this->get(route('reportes'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));
        $this->get(route('reportes'))->assertOk();
    }

    #[Test]
    public function el_panel_muestra_las_entradas_del_tramo(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));
        $ana = Persona::create(['cedula' => '1', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA PÉREZ', 'activo' => true]);
        Movimiento::create(['persona_id' => $ana->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => CarbonImmutable::today()->setTime(8, 0)]);

        Livewire::test(Panel::class)
            ->assertOk()
            ->assertSee('ANA PÉREZ')
            ->assertSee('Entradas');
    }

    #[Test]
    public function el_tramo_se_recorta_al_maximo_de_dias(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));

        // Un «desde» absurdamente lejano se recorta a MAXIMO_DIAS contados desde el «hasta».
        $componente = Livewire::test(Panel::class)
            ->set('hasta', '2026-08-31')
            ->set('desde', '2020-01-01');

        $tramo = $componente->instance()->tramo();
        $this->assertSame(Reportes::MAXIMO_DIAS, (int) $tramo['desde']->diffInDays($tramo['hasta']) + 1);
        $this->assertSame('2026-08-31', $tramo['hasta']->toDateString());
    }

    #[Test]
    public function las_fechas_al_reves_se_enderezan(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));

        $componente = Livewire::test(Panel::class)
            ->set('desde', '2026-08-31')
            ->set('hasta', '2026-08-01');

        $tramo = $componente->instance()->tramo();
        $this->assertSame('2026-08-01', $tramo['desde']->toDateString());
        $this->assertSame('2026-08-31', $tramo['hasta']->toDateString());
    }
}
