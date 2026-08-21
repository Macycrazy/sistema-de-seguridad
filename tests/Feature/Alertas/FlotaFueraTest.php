<?php

namespace Tests\Feature\Alertas;

use App\Models\Parametro;
use App\Models\VehiculoDeFlota;
use App\Models\VehiculoFijo;
use App\Services\Alertas\Alerta;
use App\Services\Alertas\Alertas;
use App\Services\DatosVehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El aviso de que un vehículo de la empresa salió y no ha vuelto.
 *
 * Sale para un trámite y vuelve. Si no vuelve, alguien tiene que enterarse sin ir a mirarlo
 * vehículo por vehículo, que es lo que no se hace nunca.
 */
class FlotaFueraTest extends TestCase
{
    use RefreshDatabase;

    private function flota(string $placa = 'EMP001'): VehiculoDeFlota
    {
        return VehiculoDeFlota::create(['placa' => $placa, 'tipo_vehiculo' => DatosVehiculo::CARRO, 'marca' => 'Toyota']);
    }

    private function estadia(VehiculoDeFlota $flota, ?string $salioHace, array $extra = []): VehiculoFijo
    {
        return VehiculoFijo::create(array_merge([
            'placa' => $flota->placa,
            'tipo_vehiculo' => $flota->tipo_vehiculo,
            'flota_id' => $flota->id,
            'entro_en' => now()->subDays(3),
            'salio_en' => $salioHace === null ? null : now()->sub($salioHace),
        ], $extra));
    }

    /** @return Collection<int, Alerta> */
    private function deFlota()
    {
        return app(Alertas::class)->activas()->where('tipo', Alerta::FLOTA_FUERA)->values();
    }

    #[Test]
    public function avisa_del_que_salio_y_no_ha_vuelto(): void
    {
        $flota = $this->flota();
        $this->estadia($flota, '10 hours', ['salida_conductor_nombre' => 'ANA PÉREZ']);

        $alertas = $this->deFlota();

        $this->assertCount(1, $alertas);
        $this->assertStringContainsString('EMP001', $alertas[0]->titulo);
        $this->assertStringContainsString('ANA PÉREZ', $alertas[0]->detalle);
    }

    #[Test]
    public function no_avisa_del_que_esta_dentro(): void
    {
        $this->estadia($this->flota(), null);

        $this->assertCount(0, $this->deFlota());
    }

    #[Test]
    public function no_avisa_del_que_salio_hace_un_rato(): void
    {
        // El plazo por omisión son 8 horas: un trámite de la mañana no es una alerta.
        $this->estadia($this->flota(), '2 hours');

        $this->assertCount(0, $this->deFlota());
    }

    #[Test]
    public function no_avisa_del_que_nunca_ha_estado_aqui(): void
    {
        // Está en el catálogo pero no ha pisado el sitio: no se ha ido a ninguna parte, y avisar
        // de él sería ruido desde el día que se carga.
        $this->flota();

        $this->assertCount(0, $this->deFlota());
    }

    #[Test]
    public function al_doble_del_plazo_pasa_a_urgente(): void
    {
        $this->estadia($this->flota(), '20 hours');

        $this->assertTrue($this->deFlota()[0]->esUrgente());
    }

    #[Test]
    public function en_cero_el_aviso_queda_apagado(): void
    {
        Parametro::updateOrCreate(['clave' => 'alerta_horas_flota_fuera'], ['valor' => 0]);
        $this->estadia($this->flota(), '10 days');

        $this->assertCount(0, $this->deFlota());
    }

    #[Test]
    public function el_que_volvio_a_entrar_deja_de_avisar(): void
    {
        // Se mira su ÚLTIMA estadía: salió el lunes, volvió el martes y sigue aquí.
        $flota = $this->flota();
        $this->estadia($flota, '3 days');
        $this->estadia($flota, null, ['entro_en' => now()->subHours(2)]);

        $this->assertCount(0, $this->deFlota());
    }
}
