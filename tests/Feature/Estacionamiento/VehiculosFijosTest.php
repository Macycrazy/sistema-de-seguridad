<?php

namespace Tests\Feature\Estacionamiento;

use App\Livewire\Estacionamiento\Panel;
use App\Models\Puesto;
use App\Models\User;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use App\Services\Estacionamiento\VehiculosFijos;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VehiculosFijosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anota_un_vehiculo_fijo_en_un_puesto_libre(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);

        app(VehiculosFijos::class)->registrar('ab123cd', DatosVehiculo::CARRO, $puesto->id, nota: 'Flota');

        $this->assertDatabaseHas('vehiculos_fijos', ['placa' => 'AB123CD', 'puesto_id' => $puesto->id, 'salio_en' => null]);
    }

    #[Test]
    public function un_fijo_ocupa_su_puesto_para_todos(): void
    {
        $a1 = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        Puesto::create(['codigo' => 'A-2', 'orden' => 2]);

        app(VehiculosFijos::class)->registrar('AB123CD', DatosVehiculo::CARRO, $a1->id);

        $est = app(Estacionamiento::class);
        $this->assertEqualsCanonicalizing([$a1->id], $est->puestosOcupados()->all());
        $this->assertSame(['A-2'], $est->puestosLibres()->pluck('codigo')->all());
    }

    #[Test]
    public function no_se_anota_un_fijo_en_un_puesto_ocupado(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        app(VehiculosFijos::class)->registrar('AAA111', DatosVehiculo::CARRO, $puesto->id);

        $this->expectException(ValidationException::class);
        app(VehiculosFijos::class)->registrar('BBB222', DatosVehiculo::CARRO, $puesto->id);
    }

    #[Test]
    public function sin_placa_o_sin_puesto_no_se_anota(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);

        try {
            app(VehiculosFijos::class)->registrar('   ', DatosVehiculo::CARRO, $puesto->id);
            $this->fail('Debió exigir la placa.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('placaFija', $e->errors());
        }

        $this->expectException(ValidationException::class);
        app(VehiculosFijos::class)->registrar('AB123CD', DatosVehiculo::CARRO, null);
    }

    #[Test]
    public function al_sacarlo_su_puesto_queda_libre(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $fijo = app(VehiculosFijos::class)->registrar('AB123CD', DatosVehiculo::CARRO, $puesto->id);

        app(VehiculosFijos::class)->sacar($fijo);

        $this->assertNotNull($fijo->fresh()->salio_en);
        $this->assertTrue(app(Estacionamiento::class)->puestosOcupados()->isEmpty());
    }

    #[Test]
    public function desde_el_panel_se_anota_y_se_saca_un_fijo(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::VIGILANTE]));
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);

        Livewire::test(Panel::class)
            ->call('abrirFijo')
            ->set('placaFija', 'AB123CD')
            ->set('tipoFija', DatosVehiculo::CARRO)
            ->set('puestoFijo', (string) $puesto->id)
            ->call('agregarFijo')
            ->assertHasNoErrors();

        $fijo = VehiculoFijo::firstOrFail();
        $this->assertSame('AB123CD', $fijo->placa);

        Livewire::test(Panel::class)->call('sacarFijo', $fijo->id);
        $this->assertNotNull($fijo->fresh()->salio_en);
    }
}
