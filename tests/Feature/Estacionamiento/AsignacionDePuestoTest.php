<?php

namespace Tests\Feature\Estacionamiento;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Puesto;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use App\Services\Marcaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsignacionDePuestoTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $cedula = '12345678'): Persona
    {
        return Persona::create(['cedula' => $cedula, 'tipo' => Persona::TRABAJADOR, 'nombre' => 'P '.$cedula, 'activo' => true]);
    }

    private function carro(string $placa = 'AB123CD'): DatosVehiculo
    {
        return DatosVehiculo::desde(DatosVehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', $placa);
    }

    #[Test]
    public function al_entrar_con_vehiculo_se_le_puede_asignar_un_puesto(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'tipo' => DatosVehiculo::CARRO, 'orden' => 1]);
        $ana = $this->persona();

        $movimiento = app(Marcaje::class)->registrar(
            persona: $ana, tipo: Movimiento::ENTRADA, vehiculo: $this->carro(), puestoId: $puesto->id,
        );

        $this->assertSame($puesto->id, $movimiento->puesto_id);
    }

    #[Test]
    public function un_puesto_ocupado_no_se_asigna_a_otro(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        app(Marcaje::class)->registrar(persona: $this->persona('1'), tipo: Movimiento::ENTRADA, vehiculo: $this->carro('AAA111'), puestoId: $puesto->id);

        $this->expectException(ValidationException::class);
        app(Marcaje::class)->registrar(persona: $this->persona('2'), tipo: Movimiento::ENTRADA, vehiculo: $this->carro('BBB222'), puestoId: $puesto->id);
    }

    #[Test]
    public function un_puesto_de_moto_no_acepta_un_carro(): void
    {
        $puesto = Puesto::create(['codigo' => 'M-1', 'tipo' => DatosVehiculo::MOTO, 'orden' => 1]);

        $this->expectException(ValidationException::class);
        app(Marcaje::class)->registrar(persona: $this->persona(), tipo: Movimiento::ENTRADA, vehiculo: $this->carro(), puestoId: $puesto->id);
    }

    #[Test]
    public function libres_y_ocupados_reflejan_lo_que_hay_dentro(): void
    {
        $a1 = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        Puesto::create(['codigo' => 'A-2', 'orden' => 2]);
        Puesto::create(['codigo' => 'A-3', 'activo' => false, 'orden' => 3]);   // deshabilitado

        app(Marcaje::class)->registrar(persona: $this->persona(), tipo: Movimiento::ENTRADA, vehiculo: $this->carro(), puestoId: $a1->id);

        $est = app(Estacionamiento::class);

        $this->assertEqualsCanonicalizing([$a1->id], $est->puestosOcupados()->all());
        // Libres: solo A-2 (A-1 ocupado, A-3 deshabilitado).
        $this->assertSame(['A-2'], $est->puestosLibres()->pluck('codigo')->all());
    }

    #[Test]
    public function se_asigna_el_puesto_despues_de_entrar_desde_el_estacionamiento(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $ana = $this->persona();
        // Entra sin puesto: en la puerta no se sabe dónde va a estacionar.
        app(Marcaje::class)->registrar(persona: $ana, tipo: Movimiento::ENTRADA, vehiculo: $this->carro());

        app(Estacionamiento::class)->asignarPuesto($ana->id, $puesto->id);

        $this->assertSame($puesto->id, (int) Movimiento::where('persona_id', $ana->id)->where('tipo', Movimiento::ENTRADA)->value('puesto_id'));
        $this->assertEqualsCanonicalizing([$puesto->id], app(Estacionamiento::class)->puestosOcupados()->all());
    }

    #[Test]
    public function no_se_le_asigna_un_puesto_ya_ocupado_por_otro(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $ana = $this->persona('1');
        app(Marcaje::class)->registrar(persona: $ana, tipo: Movimiento::ENTRADA, vehiculo: $this->carro('AAA111'), puestoId: $puesto->id);

        $luis = $this->persona('2');
        app(Marcaje::class)->registrar(persona: $luis, tipo: Movimiento::ENTRADA, vehiculo: $this->carro('BBB222'));

        $this->expectException(ValidationException::class);
        app(Estacionamiento::class)->asignarPuesto($luis->id, $puesto->id);
    }

    #[Test]
    public function reasignar_al_mismo_puesto_no_falla(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $ana = $this->persona();
        app(Marcaje::class)->registrar(persona: $ana, tipo: Movimiento::ENTRADA, vehiculo: $this->carro(), puestoId: $puesto->id);

        // No revienta: sigue siendo su plaza.
        app(Estacionamiento::class)->asignarPuesto($ana->id, $puesto->id);

        $this->assertSame($puesto->id, (int) Movimiento::where('persona_id', $ana->id)->value('puesto_id'));
    }

    #[Test]
    public function quitar_el_puesto_deja_al_vehiculo_sin_plaza(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $ana = $this->persona();
        app(Marcaje::class)->registrar(persona: $ana, tipo: Movimiento::ENTRADA, vehiculo: $this->carro(), puestoId: $puesto->id);

        app(Estacionamiento::class)->asignarPuesto($ana->id, null);

        $this->assertNull(Movimiento::where('persona_id', $ana->id)->value('puesto_id'));
        $this->assertSame(['A-1'], app(Estacionamiento::class)->puestosLibres()->pluck('codigo')->all());
    }

    #[Test]
    public function al_salir_el_puesto_queda_libre_otra_vez(): void
    {
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $ana = $this->persona();

        app(Marcaje::class)->registrar(persona: $ana, tipo: Movimiento::ENTRADA, vehiculo: $this->carro(), puestoId: $puesto->id);
        // La entrada, una hora atrás: si no, el mínimo entre entrada y salida rechaza la salida.
        Movimiento::where('persona_id', $ana->id)->update(['ocurrio_en' => now()->subHour()]);
        app(Marcaje::class)->registrar(persona: $ana, tipo: Movimiento::SALIDA);

        $this->assertTrue(app(Estacionamiento::class)->puestosOcupados()->isEmpty());
        $this->assertSame(['A-1'], app(Estacionamiento::class)->puestosLibres()->pluck('codigo')->all());
    }
}
