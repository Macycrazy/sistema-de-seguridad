<?php

namespace Tests\Feature\Estacionamiento;

use App\Models\Puesto;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Asignar el puesto a un vehículo que está dentro (una estadía). Lo hace quien está en el
 * estacionamiento, que ve dónde quedó; la puerta ya no maneja vehículos ni puestos.
 */
class AsignacionDePuestoTest extends TestCase
{
    use RefreshDatabase;

    private function estadia(string $placa = 'AB123CD', string $tipo = DatosVehiculo::CARRO): VehiculoFijo
    {
        return VehiculoFijo::create([
            'placa' => $placa,
            'tipo_vehiculo' => $tipo,
            'entro_en' => CarbonImmutable::now()->subHour(),
        ]);
    }

    #[Test]
    public function se_le_asigna_el_puesto_a_un_vehiculo_dentro(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $estadia = $this->estadia();

        app(Estacionamiento::class)->asignarPuesto($estadia->id, $puesto->id);

        $this->assertSame($puesto->id, $estadia->fresh()->puesto_id);
        $this->assertEqualsCanonicalizing([$puesto->id], app(Estacionamiento::class)->puestosOcupados()->all());
    }

    #[Test]
    public function no_se_le_asigna_un_puesto_ocupado_por_otro(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $uno = $this->estadia('AAA111');
        app(Estacionamiento::class)->asignarPuesto($uno->id, $puesto->id);

        $dos = $this->estadia('BBB222');

        $this->expectException(ValidationException::class);
        app(Estacionamiento::class)->asignarPuesto($dos->id, $puesto->id);
    }

    #[Test]
    public function un_puesto_de_moto_no_acepta_un_carro(): void
    {
        $puesto = Puesto::create(['codigo' => 'M-1', 'tipo' => DatosVehiculo::MOTO, 'orden' => 1]);
        $estadia = $this->estadia('AB123CD', DatosVehiculo::CARRO);

        $this->expectException(ValidationException::class);
        app(Estacionamiento::class)->asignarPuesto($estadia->id, $puesto->id);
    }

    #[Test]
    public function reasignar_al_mismo_puesto_no_falla(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $estadia = $this->estadia();
        app(Estacionamiento::class)->asignarPuesto($estadia->id, $puesto->id);

        app(Estacionamiento::class)->asignarPuesto($estadia->id, $puesto->id);

        $this->assertSame($puesto->id, $estadia->fresh()->puesto_id);
    }

    #[Test]
    public function quitar_el_puesto_deja_al_vehiculo_sin_plaza(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $estadia = $this->estadia();
        app(Estacionamiento::class)->asignarPuesto($estadia->id, $puesto->id);

        app(Estacionamiento::class)->asignarPuesto($estadia->id, null);

        $this->assertNull($estadia->fresh()->puesto_id);
        $this->assertSame(['A-1'], app(Estacionamiento::class)->puestosLibres()->pluck('codigo')->all());
    }
}
