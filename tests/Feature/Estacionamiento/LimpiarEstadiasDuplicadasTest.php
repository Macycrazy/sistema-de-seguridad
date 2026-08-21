<?php

namespace Tests\Feature\Estacionamiento;

use App\Models\VehiculoFijo;
use App\Services\Auditoria\Auditoria;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La limpieza de vehículos que figuran dentro más de una vez.
 *
 * Es el arrastre de antes de la regla que lo impide, y daba la cara así: se le marcaba la salida
 * al carro y el carro seguía apareciendo dentro.
 */
class LimpiarEstadiasDuplicadasTest extends TestCase
{
    use RefreshDatabase;

    private function estadia(string $placa, string $cuando): VehiculoFijo
    {
        return VehiculoFijo::create([
            'placa' => $placa,
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'entro_en' => CarbonImmutable::parse($cuando),
        ]);
    }

    #[Test]
    public function en_seco_no_toca_nada(): void
    {
        $this->estadia('ABC123', '-3 hours');
        $this->estadia('ABC123', '-1 hour');

        $this->artisan('estacionamiento:duplicados')
            ->expectsOutputToContain('figuran dentro más de una vez')
            ->expectsOutputToContain('Es una simulación')
            ->assertSuccessful();

        $this->assertSame(2, VehiculoFijo::abiertos()->count());
    }

    #[Test]
    public function con_confirmar_deja_una_por_vehiculo_y_conserva_la_mas_reciente(): void
    {
        $vieja = $this->estadia('ABC123', '-3 hours');
        $reciente = $this->estadia('ABC123', '-1 hour');
        $otro = $this->estadia('ZZZ999', '-2 hours');

        $this->artisan('estacionamiento:duplicados', ['--confirmar' => true])
            ->expectsOutputToContain('Cerradas 1')
            ->assertSuccessful();

        $this->assertNotNull($vieja->fresh()->salio_en, 'La vieja sobra.');
        $this->assertNull($reciente->fresh()->salio_en, 'La reciente dice dónde está ahora.');
        $this->assertNull($otro->fresh()->salio_en, 'El de otra placa no se toca.');
        $this->assertSame(2, app(Estacionamiento::class)->cuantosDentro());

        // No se inventa quién se lo llevó: no se lo llevó nadie, era una fila que sobraba.
        $this->assertNull($vieja->fresh()->salida_conductor_id);
        $this->assertStringContainsString('duplicada', (string) $vieja->fresh()->nota);
        $this->assertDatabaseHas('bitacora', ['accion' => Auditoria::CERRO_DUPLICADA, 'sobre' => 'ABC123']);
    }

    #[Test]
    public function sin_duplicados_lo_dice_y_no_hace_nada(): void
    {
        $this->estadia('ABC123', '-1 hour');

        $this->artisan('estacionamiento:duplicados')
            ->expectsOutputToContain('No hay ningún vehículo anotado dentro más de una vez')
            ->assertSuccessful();
    }
}
