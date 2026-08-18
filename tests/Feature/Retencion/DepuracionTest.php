<?php

namespace Tests\Feature\Retencion;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Retencion\Depuracion;
use App\Services\Retencion\RetencionDeDatos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DepuracionTest extends TestCase
{
    use RefreshDatabase;

    private function persona(): Persona
    {
        return Persona::create(['cedula' => '12345678', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA', 'activo' => true]);
    }

    private function movimiento(Persona $p, string $cuando): Movimiento
    {
        return Movimiento::create(['persona_id' => $p->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => $cuando]);
    }

    #[Test]
    public function desactivada_no_borra_nada(): void
    {
        $ana = $this->persona();
        $this->movimiento($ana, CarbonImmutable::now()->subYears(5)->toDateTimeString());

        app(Depuracion::class)->ejecutar();

        $this->assertSame(1, Movimiento::count());
        $this->assertFalse(app(Depuracion::class)->estaActiva());
    }

    #[Test]
    public function borra_lo_mas_viejo_que_el_corte_y_conserva_lo_reciente(): void
    {
        Storage::fake('local');
        app(RetencionDeDatos::class)->guardar('retencion_movimientos_meses', 12);

        $ana = $this->persona();
        $viejo = $this->movimiento($ana, CarbonImmutable::now()->subMonths(13)->toDateTimeString());
        $reciente = $this->movimiento($ana, CarbonImmutable::now()->subMonths(1)->toDateTimeString());

        $informe = app(Depuracion::class)->ejecutar();

        $this->assertDatabaseMissing('movimientos', ['id' => $viejo->id]);
        $this->assertDatabaseHas('movimientos', ['id' => $reciente->id]);
        // Personas NO se toca.
        $this->assertDatabaseHas('personas', ['id' => $ana->id]);
        // Se archivó antes de borrar.
        $movimientos = collect($informe)->firstWhere('tabla', 'movimientos');
        $this->assertSame(1, $movimientos['cuantos']);
        Storage::disk('local')->assertExists($movimientos['archivo']);
    }

    #[Test]
    public function el_plan_no_borra_nada(): void
    {
        app(RetencionDeDatos::class)->guardar('retencion_movimientos_meses', 12);
        $ana = $this->persona();
        $this->movimiento($ana, CarbonImmutable::now()->subMonths(13)->toDateTimeString());

        $plan = app(Depuracion::class)->plan();

        $this->assertSame(1, collect($plan)->firstWhere('tabla', 'movimientos')['cuantos']);
        $this->assertSame(1, Movimiento::count());   // nada borrado
    }

    #[Test]
    public function sin_nada_viejo_no_escribe_archivo(): void
    {
        Storage::fake('local');
        app(RetencionDeDatos::class)->guardar('retencion_movimientos_meses', 12);
        $ana = $this->persona();
        $this->movimiento($ana, CarbonImmutable::now()->subMonths(1)->toDateTimeString());

        $informe = app(Depuracion::class)->ejecutar();

        $movimientos = collect($informe)->firstWhere('tabla', 'movimientos');
        $this->assertSame(0, $movimientos['cuantos']);
        $this->assertNull($movimientos['archivo']);
    }
}
