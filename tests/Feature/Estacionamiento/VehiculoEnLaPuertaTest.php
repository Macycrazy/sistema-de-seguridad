<?php

namespace Tests\Feature\Estacionamiento;

use App\Livewire\Marcar;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Vehiculo;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use App\Services\Marcaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El vehículo anotado en el mismo gesto de marcar a la persona.
 *
 * Antes eran dos formularios y la cédula del conductor se tecleaba aparte —opcional, así que con
 * cola detrás no se tecleaba—. Lo que se prueba aquí es que ahora el conductor sale gratis: es
 * quien se está marcando.
 */
class VehiculoEnLaPuertaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entrandoComo();
    }

    private function trabajador(array $atributos = []): Persona
    {
        return Persona::create(array_merge([
            'cedula' => '12345678',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'ANA RODRÍGUEZ',
            'dependencia' => 'RECURSOS HUMANOS',
            'activo' => true,
        ], $atributos));
    }

    #[Test]
    public function marcar_a_pie_no_anota_ningun_vehiculo(): void
    {
        // Lo normal, y lo que no puede cambiar: quien viene caminando no toca nada de más.
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('A pie')                                   // es una opción visible
            ->assertSet('vehiculoEntrada', Marcar::VEHICULO_A_PIE)  // y viene elegida
            ->call('marcarEntrada')
            ->assertHasNoErrors();

        $this->assertSame(0, app(Estacionamiento::class)->cuantosDentro());
    }

    #[Test]
    public function el_boton_de_a_pie_deshace_un_vehiculo_elegido_por_error(): void
    {
        // Quien toca el carro sin querer tiene que poder volver atrás sin recargar la pantalla.
        $ana = $this->trabajador();
        Vehiculo::create(['persona_id' => $ana->id, 'tipo' => DatosVehiculo::CARRO, 'placa' => 'AB123CD']);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('elegirVehiculo', 'AB123CD')
            ->assertSet('vehiculoEntrada', 'AB123CD')
            ->call('elegirVehiculo', Marcar::VEHICULO_A_PIE)
            ->assertSet('vehiculoEntrada', Marcar::VEHICULO_A_PIE)
            ->call('marcarEntrada')
            ->assertHasNoErrors();

        $this->assertSame(0, app(Estacionamiento::class)->cuantosDentro());
    }

    #[Test]
    public function el_boton_de_salir_a_pie_desmarca_el_vehiculo_que_se_iba_a_llevar(): void
    {
        $this->trabajador();

        $componente = Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('elegirVehiculo', Marcar::VEHICULO_OTRO)
            ->set('placaNueva', 'AB123CD')
            ->call('marcarEntrada');

        $estadia = VehiculoFijo::where('placa', 'AB123CD')->firstOrFail();
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        $componente
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('alternarVehiculoSalida', $estadia->id)
            ->assertSet('vehiculosSalida', [$estadia->id])
            ->call('salirAPie')
            ->assertSet('vehiculosSalida', [])
            ->call('marcarSalida')
            ->assertHasNoErrors();

        $this->assertNull($estadia->fresh()->salio_en, 'El carro se queda dentro.');
    }

    #[Test]
    public function al_entrar_con_una_placa_nueva_se_abre_su_estadia_a_su_nombre(): void
    {
        $ana = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('elegirVehiculo', Marcar::VEHICULO_OTRO)
            ->set('placaNueva', 'ab-123-cd')
            ->set('tipoNuevo', DatosVehiculo::MOTO)
            ->call('marcarEntrada')
            ->assertHasNoErrors();

        // El conductor no se tecleó en ninguna parte: es quien se estaba marcando.
        $this->assertDatabaseHas('vehiculos_fijos', [
            'placa' => 'AB123CD',
            'tipo_vehiculo' => DatosVehiculo::MOTO,
            'conductor_id' => $ana->id,
            'salio_en' => null,
        ]);
    }

    #[Test]
    public function la_placa_tecleada_queda_en_su_ficha_para_la_proxima_vez(): void
    {
        // Es lo que hace que la segunda vez sea un toque en vez de teclear otra vez la placa.
        $ana = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('elegirVehiculo', Marcar::VEHICULO_OTRO)
            ->set('placaNueva', 'AB123CD')
            ->call('marcarEntrada');

        $this->assertDatabaseHas('vehiculos', ['persona_id' => $ana->id, 'placa' => 'AB123CD']);
    }

    #[Test]
    public function con_un_vehiculo_ya_guardado_entrar_es_un_toque(): void
    {
        $ana = $this->trabajador();
        Vehiculo::create(['persona_id' => $ana->id, 'tipo' => DatosVehiculo::CARRO, 'placa' => 'AB123CD']);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('AB123CD')             // sale para elegirlo
            ->call('elegirVehiculo', 'AB123CD')
            ->call('marcarEntrada')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehiculos_fijos', ['placa' => 'AB123CD', 'conductor_id' => $ana->id, 'salio_en' => null]);
    }

    #[Test]
    public function al_salir_se_le_marca_la_salida_al_vehiculo_que_se_lleva(): void
    {
        $ana = $this->trabajador();

        $componente = Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('elegirVehiculo', Marcar::VEHICULO_OTRO)
            ->set('placaNueva', 'AB123CD')
            ->call('marcarEntrada');

        $estadia = VehiculoFijo::where('placa', 'AB123CD')->firstOrFail();

        // La puerta no deja marcar la salida recién entrado: se espera lo que manda la regla.
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        $componente
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('AB123CD')
            ->call('alternarVehiculoSalida', $estadia->id)
            ->call('marcarSalida')
            ->assertHasNoErrors();

        $estadia->refresh();

        $this->assertNotNull($estadia->salio_en);
        $this->assertSame($ana->id, $estadia->salida_conductor_id);
        $this->assertSame(0, app(Estacionamiento::class)->cuantosDentro());
    }

    #[Test]
    public function quien_sale_a_pie_deja_su_vehiculo_dentro(): void
    {
        // Se sale a pie muchas veces al día —el almuerzo, un trámite—: el carro sigue ahí y la
        // estadía no puede cerrarse sola.
        $this->trabajador();

        $componente = Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('elegirVehiculo', Marcar::VEHICULO_OTRO)
            ->set('placaNueva', 'AB123CD')
            ->call('marcarEntrada');

        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        $componente
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarSalida')          // sin tocar el vehículo
            ->assertHasNoErrors();

        $this->assertSame(1, app(Estacionamiento::class)->cuantosDentro(), 'El carro se queda.');
    }

    #[Test]
    public function no_se_puede_salir_con_un_vehiculo_que_ya_no_esta(): void
    {
        // La regla: no se sale con un vehículo que no está. Si entretanto se lo llevó otro, hay
        // que decirlo y no tragárselo.
        $ana = $this->trabajador();

        // Ana está dentro: si no, la puerta ni siquiera le deja marcar la salida.
        app(Marcaje::class)->registrar($ana, Movimiento::ENTRADA);
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        $estadia = VehiculoFijo::create([
            'placa' => 'AB123CD',
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'entro_en' => now()->subHour(),
            'conductor_id' => $ana->id,
            'salio_en' => now()->subMinutes(5),
        ]);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('alternarVehiculoSalida', $estadia->id)
            ->call('marcarSalida')
            ->assertHasErrors('vehiculoSalida');
    }

    #[Test]
    public function el_mismo_vehiculo_no_puede_entrar_dos_veces(): void
    {
        $ana = $this->trabajador();
        $luis = $this->trabajador(['cedula' => '87654321', 'nombre' => 'LUIS GÓMEZ']);

        VehiculoFijo::create([
            'placa' => 'AB123CD',
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'entro_en' => now()->subHour(),
            'conductor_id' => $ana->id,
        ]);

        // Otro intenta meter el mismo carro: contaría doble en el aforo y ocuparía dos plazas.
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->call('elegirVehiculo', Marcar::VEHICULO_OTRO)
            ->set('placaNueva', 'AB123CD')
            ->call('marcarEntrada')
            ->assertHasErrors();

        $this->assertSame(1, VehiculoFijo::abiertos()->where('placa', 'AB123CD')->count());
        $this->assertNotNull($luis->id);
    }

    #[Test]
    public function el_vehiculo_de_uno_no_se_queda_marcado_al_pasar_al_siguiente(): void
    {
        $ana = $this->trabajador();
        Vehiculo::create(['persona_id' => $ana->id, 'tipo' => DatosVehiculo::CARRO, 'placa' => 'AB123CD']);
        $this->trabajador(['cedula' => '87654321', 'nombre' => 'LUIS GÓMEZ']);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('elegirVehiculo', 'AB123CD')
            ->set('cedula', '87654321')
            ->call('buscar')
            ->assertSet('vehiculoEntrada', '')
            ->call('marcarEntrada')
            ->assertHasNoErrors();

        // Luis entró a pie: el carro de Ana no se le pegó.
        $this->assertSame(0, app(Estacionamiento::class)->cuantosDentro());
    }

    #[Test]
    public function el_movimiento_de_la_persona_se_registra_aunque_el_vehiculo_falle(): void
    {
        // El asiento es lo que no puede perderse: el carro se arregla desde el estacionamiento.
        $ana = $this->trabajador();

        VehiculoFijo::create([
            'placa' => 'AB123CD',
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'entro_en' => now()->subHour(),
        ]);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('elegirVehiculo', Marcar::VEHICULO_OTRO)
            ->set('placaNueva', 'AB123CD')      // ya está dentro: el vehículo va a fallar
            ->call('marcarEntrada')
            ->assertHasErrors();

        $this->assertDatabaseHas('movimientos', ['persona_id' => $ana->id, 'tipo' => Movimiento::ENTRADA]);
    }
}
