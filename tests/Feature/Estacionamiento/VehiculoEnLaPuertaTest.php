<?php

namespace Tests\Feature\Estacionamiento;

use App\Livewire\Marcar;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Vehiculo;
use App\Models\VehiculoDeFlota;
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
    public function se_puede_salir_con_el_vehiculo_de_un_companero(): void
    {
        // Pasa de verdad: se llega en el carro propio y se sale en la moto de un compañero. Si no
        // se pudiera anotar aquí, esa moto se quedaría figurando dentro sin estar.
        $ana = $this->trabajador();
        $luis = $this->trabajador(['cedula' => '87654321', 'nombre' => 'LUIS GÓMEZ']);

        $moto = VehiculoFijo::create([
            'placa' => 'MOTO123',
            'tipo_vehiculo' => DatosVehiculo::MOTO,
            'entro_en' => now()->subHours(2),
            'conductor_id' => $luis->id,
        ]);

        app(Marcaje::class)->registrar($ana, Movimiento::ENTRADA);
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('MOTO123')                       // sale entre los que están dentro
            ->set('otroVehiculoSalida', (string) $moto->id)
            ->call('llevarseOtro')
            ->assertSet('vehiculosSalida', [$moto->id])
            ->call('marcarSalida')
            ->assertHasNoErrors();

        $moto->refresh();

        $this->assertNotNull($moto->salio_en);
        $this->assertSame($ana->id, $moto->salida_conductor_id, 'Se la llevó Ana, no Luis.');
    }

    #[Test]
    public function entrar_con_un_vehiculo_de_la_empresa_lo_enlaza_a_la_flota_y_no_a_su_ficha(): void
    {
        // Un carro de la empresa no es suyo: no puede quedarse en su ficha como propio.
        $ana = $this->trabajador();

        VehiculoDeFlota::create([
            'placa' => 'EMP001',
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'marca' => 'Toyota',
        ]);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('elegirVehiculo', Marcar::VEHICULO_OTRO)
            ->set('placaNueva', 'emp001')
            ->call('marcarEntrada')
            ->assertHasNoErrors();

        $estadia = VehiculoFijo::where('placa', 'EMP001')->firstOrFail();

        $this->assertNotNull($estadia->flota_id, 'Debería quedar enlazada al catálogo de la empresa.');
        $this->assertSame($ana->id, $estadia->conductor_id);
        $this->assertDatabaseMissing('vehiculos', ['persona_id' => $ana->id, 'placa' => 'EMP001']);
    }

    #[Test]
    public function un_vehiculo_de_la_empresa_se_trae_eligiendolo_del_catalogo(): void
    {
        // Los de la empresa no son de nadie: no están en la ficha de ninguna persona, así que se
        // eligen del catálogo. Cualquiera puede traerlos.
        $ana = $this->trabajador();
        $flota = VehiculoDeFlota::create(['placa' => 'EMP001', 'tipo_vehiculo' => DatosVehiculo::CARRO, 'marca' => 'Toyota']);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('EMP001')
            ->set('vehiculoEntrada', Marcar::PREFIJO_FLOTA.$flota->id)
            ->call('marcarEntrada')
            ->assertHasNoErrors();

        $estadia = VehiculoFijo::where('placa', 'EMP001')->firstOrFail();

        $this->assertSame($flota->id, $estadia->flota_id);
        $this->assertSame($ana->id, $estadia->conductor_id, 'Queda quién lo trajo, aunque el carro no sea suyo.');
        $this->assertDatabaseMissing('vehiculos', ['persona_id' => $ana->id, 'placa' => 'EMP001']);
    }

    #[Test]
    public function un_vehiculo_de_la_empresa_que_esta_dentro_no_se_ofrece_para_traerlo(): void
    {
        $this->trabajador();
        $flota = VehiculoDeFlota::create(['placa' => 'EMP001', 'tipo_vehiculo' => DatosVehiculo::CARRO]);

        VehiculoFijo::create([
            'placa' => 'EMP001',
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'flota_id' => $flota->id,
            'entro_en' => now()->subDay(),
        ]);

        $componente = Livewire::test(Marcar::class)->set('cedula', '12345678')->call('buscar');

        $this->assertSame([], $componente->instance()->flotaParaEntrar);
    }

    #[Test]
    public function cualquiera_puede_llevarse_un_vehiculo_de_la_empresa_que_no_tiene_conductor(): void
    {
        // El caso del carro de la empresa que vive en el estacionamiento: no lo trajo nadie hoy,
        // no es de nadie, y aun así alguien tiene que poder sacarlo dejando constancia.
        $ana = $this->trabajador();
        $flota = VehiculoDeFlota::create(['placa' => 'EMP001', 'tipo_vehiculo' => DatosVehiculo::CARRO]);

        $estadia = VehiculoFijo::create([
            'placa' => 'EMP001',
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'flota_id' => $flota->id,
            'entro_en' => now()->subDays(2),
            'conductor_id' => null,
        ]);

        app(Marcaje::class)->registrar($ana, Movimiento::ENTRADA);
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('EMP001')
            ->set('otroVehiculoSalida', (string) $estadia->id)
            ->call('llevarseOtro')
            ->call('marcarSalida')
            ->assertHasNoErrors();

        $estadia->refresh();

        $this->assertNotNull($estadia->salio_en);
        $this->assertSame($ana->id, $estadia->salida_conductor_id, 'Queda quién se lo llevó.');
    }

    #[Test]
    public function si_toda_la_flota_esta_dentro_la_puerta_lo_dice_en_vez_de_callarse(): void
    {
        // «No hay ninguno para traer» y «no hay flota» se ven igual —la pantalla no enseña nada—
        // y no son lo mismo: a media mañana están todos aquí.
        $this->trabajador();
        $flota = VehiculoDeFlota::create(['placa' => 'EMP001', 'tipo_vehiculo' => DatosVehiculo::CARRO]);

        VehiculoFijo::create([
            'placa' => 'EMP001',
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'flota_id' => $flota->id,
            'entro_en' => now()->subDay(),
        ]);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Todos los vehículos de la empresa están dentro');
    }

    #[Test]
    public function sin_flota_cargada_la_puerta_no_dice_nada_de_la_empresa(): void
    {
        // Ni el desplegable ni el aviso: sin catálogo no hay nada que decir. (El texto de la
        // ayuda sí la menciona siempre, y por eso se buscan las frases del bloque.)
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertDontSee('Todos los vehículos de la empresa')
            ->assertDontSee('vehiculoDeLaEmpresa');   // el id del desplegable: no se ha pintado
    }

    #[Test]
    public function elegir_un_vehiculo_del_desplegable_basta_para_llevarselo(): void
    {
        // El fallo que se veía: entrar a pie, salir con una moto de la empresa, y la moto seguía
        // dentro. Había que elegirla Y pulsar «Añadir»; quien pulsaba SALIDA sin más salía a pie
        // sin que nada lo avisara. Elegirla tiene que bastar.
        $ana = $this->trabajador();
        $flota = VehiculoDeFlota::create(['placa' => 'MOTOEMP', 'tipo_vehiculo' => DatosVehiculo::MOTO]);

        $moto = VehiculoFijo::create([
            'placa' => 'MOTOEMP',
            'tipo_vehiculo' => DatosVehiculo::MOTO,
            'flota_id' => $flota->id,
            'entro_en' => now()->subDay(),
        ]);

        app(Marcaje::class)->registrar($ana, Movimiento::ENTRADA);   // entró a pie
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->set('otroVehiculoSalida', (string) $moto->id)   // solo elegir, sin «Añadir»
            ->call('marcarSalida')
            ->assertHasNoErrors();

        $moto->refresh();

        $this->assertNotNull($moto->salio_en, 'La moto se fue con Ana.');
        $this->assertSame($ana->id, $moto->salida_conductor_id);
        $this->assertSame(0, app(Estacionamiento::class)->cuantosDentro());
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
