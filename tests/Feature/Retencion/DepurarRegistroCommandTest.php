<?php

namespace Tests\Feature\Retencion;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Auditoria\Auditoria;
use App\Services\Retencion\RetencionDeDatos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DepurarRegistroCommandTest extends TestCase
{
    use RefreshDatabase;

    private function movimientoViejo(): Movimiento
    {
        $ana = Persona::create(['cedula' => '12345678', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA', 'activo' => true]);

        return Movimiento::create([
            'persona_id' => $ana->id,
            'tipo' => Movimiento::ENTRADA,
            'ocurrio_en' => CarbonImmutable::now()->subMonths(13)->toDateTimeString(),
        ]);
    }

    #[Test]
    public function con_todo_en_cero_avisa_y_no_borra(): void
    {
        $this->movimientoViejo();

        $this->artisan('registro:depurar')
            ->expectsOutputToContain('desactivada')
            ->assertSuccessful();

        $this->assertSame(1, Movimiento::count());
    }

    #[Test]
    public function en_seco_no_borra(): void
    {
        Storage::fake('local');
        app(RetencionDeDatos::class)->guardar('retencion_movimientos_meses', 12);
        $this->movimientoViejo();

        $this->artisan('registro:depurar')
            ->expectsOutputToContain('simulación')
            ->assertSuccessful();

        $this->assertSame(1, Movimiento::count());
    }

    #[Test]
    public function con_confirmar_borra_y_deja_rastro(): void
    {
        Storage::fake('local');
        app(RetencionDeDatos::class)->guardar('retencion_movimientos_meses', 12);
        $this->movimientoViejo();

        $this->artisan('registro:depurar --confirmar')->assertSuccessful();

        $this->assertSame(0, Movimiento::count());
        $this->assertDatabaseHas('bitacora', ['accion' => Auditoria::DEPURO_DATOS]);
    }
}
