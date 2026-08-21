<?php

namespace Tests\Feature\Estacionamiento;

use App\Livewire\Estacionamiento\Panel;
use App\Models\Persona;
use App\Models\Puesto;
use App\Models\User;
use App\Models\VehiculoDeFlota;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use App\Services\Estacionamiento\Flota;
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
    public function se_anota_un_vehiculo_sin_puesto_y_se_le_asigna_despues(): void
    {
        // En la puerta del estacionamiento se anota el vehículo al entrar, sin saber aún la plaza.
        $fijo = app(VehiculosFijos::class)->registrar('AB123CD', DatosVehiculo::CARRO, null, conductorNombre: 'Ana');

        $this->assertNull($fijo->puesto_id);
        $this->assertDatabaseHas('vehiculos_fijos', ['placa' => 'AB123CD', 'puesto_id' => null, 'salio_en' => null]);

        // Ya está dentro (cuenta), aunque todavía sin plaza.
        $this->assertSame(1, app(Estacionamiento::class)->cuantosDentro());

        // Luego, quien está adentro le pone el puesto.
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        app(Estacionamiento::class)->asignarPuesto($fijo->id, $puesto->id);

        $this->assertSame($puesto->id, $fijo->fresh()->puesto_id);
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
    public function sin_placa_no_se_anota(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);

        $this->expectException(ValidationException::class);
        app(VehiculosFijos::class)->registrar('   ', DatosVehiculo::CARRO, $puesto->id);
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
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
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

        Livewire::test(Panel::class)
            ->call('abrirSalida', $fijo->id)
            ->set('conductorSalidaNombre', 'Otro Conductor')
            ->call('confirmarSalida')
            ->assertHasNoErrors();

        $this->assertNotNull($fijo->fresh()->salio_en);
        $this->assertSame('Otro Conductor', $fijo->fresh()->salida_conductor_nombre);
    }

    #[Test]
    public function el_conductor_por_cedula_se_liga_a_la_persona(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $ana = Persona::create(['cedula' => '12345678', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA PÉREZ', 'activo' => true]);

        $fijo = app(VehiculosFijos::class)->registrar(
            'AB123CD', DatosVehiculo::CARRO, $puesto->id, conductorCedula: '12.345.678',
        );

        $this->assertSame($ana->id, $fijo->conductor_id);
        $this->assertSame('ANA PÉREZ', $fijo->conductor_nombre);
    }

    #[Test]
    public function una_cedula_de_conductor_que_no_existe_avisa(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);

        $this->expectException(ValidationException::class);
        app(VehiculosFijos::class)->registrar('AB123CD', DatosVehiculo::CARRO, $puesto->id, conductorCedula: '99999999');
    }

    #[Test]
    public function un_vehiculo_de_la_flota_no_se_puede_anotar_dos_veces(): void
    {
        $puestoA = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        Puesto::create(['codigo' => 'A-2', 'orden' => 2]);
        $flota = VehiculoDeFlota::create(['placa' => 'EMP001', 'tipo_vehiculo' => 'carro']);

        app(VehiculosFijos::class)->registrar('EMP001', DatosVehiculo::CARRO, $puestoA->id, flotaId: $flota->id);

        // Ya está dentro: no aparece entre los disponibles de la flota.
        $this->assertTrue(app(Flota::class)->disponibles()->isEmpty());
    }
}
