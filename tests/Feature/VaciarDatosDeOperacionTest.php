<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Puesto;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoDeFlota;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El vaciado de los datos del día a día, para dejar el sistema limpio después de probarlo.
 *
 * Lo que más importa aquí no es lo que borra, sino lo que NO borra: personas, usuarios, roles y
 * puestos son lo que costó cargar.
 */
class VaciarDatosDeOperacionTest extends TestCase
{
    use RefreshDatabase;

    private function sembrar(): Persona
    {
        $persona = Persona::create(['cedula' => '11111111', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA', 'activo' => true]);
        User::factory()->create(['rol' => Rol::vigilante()]);
        Puesto::create(['codigo' => 'A-1', 'orden' => 1]);

        Movimiento::create(['persona_id' => $persona->id, 'tipo' => Movimiento::ENTRADA, 'ocurrio_en' => now()]);
        Vehiculo::create(['persona_id' => $persona->id, 'tipo' => DatosVehiculo::CARRO, 'placa' => 'AB123CD']);
        $flota = VehiculoDeFlota::create(['placa' => 'EMP001', 'tipo_vehiculo' => DatosVehiculo::CARRO]);
        VehiculoFijo::create(['placa' => 'EMP001', 'tipo_vehiculo' => DatosVehiculo::CARRO, 'flota_id' => $flota->id, 'entro_en' => now()]);
        Bitacora::create(['accion' => 'ingreso-correcto', 'ocurrio_en' => now()]);

        return $persona;
    }

    #[Test]
    public function en_seco_cuenta_pero_no_borra(): void
    {
        $this->sembrar();

        $this->artisan('sistema:vaciar')
            ->expectsOutputToContain('Se vaciaría esto')
            ->expectsOutputToContain('Es una simulación')
            ->assertSuccessful();

        $this->assertSame(1, Movimiento::count());
        $this->assertSame(1, VehiculoFijo::count());
    }

    #[Test]
    public function con_confirmar_vacia_la_operacion_y_conserva_lo_que_costo_cargar(): void
    {
        $persona = $this->sembrar();

        $this->artisan('sistema:vaciar', ['--confirmar' => true])->assertSuccessful();

        $this->assertSame(0, Movimiento::count());
        $this->assertSame(0, VehiculoFijo::count());
        $this->assertSame(0, Vehiculo::count());
        $this->assertSame(0, VehiculoDeFlota::count());

        // Lo que no se toca.
        $this->assertNotNull($persona->fresh(), 'Las personas se quedan.');
        $this->assertSame(1, User::count(), 'Los usuarios se quedan.');
        $this->assertSame(1, Puesto::count(), 'Los puestos se quedan.');
        $this->assertSame(1, Bitacora::count(), 'La bitácora no se toca salvo que se pida.');
    }

    #[Test]
    public function se_puede_vaciar_solo_un_grupo(): void
    {
        $this->sembrar();

        $this->artisan('sistema:vaciar', ['--flota' => true, '--confirmar' => true])->assertSuccessful();

        $this->assertSame(0, VehiculoDeFlota::count(), 'El catálogo se vacía…');
        $this->assertSame(1, Movimiento::count(), '…y el registro no se toca.');
    }

    #[Test]
    public function la_bitacora_solo_se_borra_si_se_pide(): void
    {
        $this->sembrar();

        $this->artisan('sistema:vaciar', ['--con-bitacora' => true, '--confirmar' => true])->assertSuccessful();

        $this->assertSame(0, Bitacora::count());
    }

    #[Test]
    public function en_produccion_no_corre_sin_decirlo_dos_veces(): void
    {
        // Ahí los movimientos son el histórico de verdad, y esto no archiva nada antes de borrar.
        $this->sembrar();
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('sistema:vaciar', ['--confirmar' => true])->assertFailed();

        $this->assertSame(1, Movimiento::count());
    }
}
