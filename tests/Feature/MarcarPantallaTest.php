<?php

namespace Tests\Feature;

use App\Livewire\Marcar;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Puesto;
use App\Services\Marcaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de la puerta, vista como la usa el vigilante: teclear una cédula y pulsar un botón.
 *
 * Lo que se comprueba aquí es el recorrido completo, incluida la promesa del módulo: marcar a
 * alguien sin escribir nada más que la cédula, y que un invitado que ya vino se marque igual.
 */
class MarcarPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Desde la parte 3, la pantalla de marcar está detrás del ingreso: el vigilante entra
        // con su usuario al empezar el turno. Aquí se prueba el recorrido, no el permiso.
        $this->entrandoComo();
    }

    private function trabajador(array $atributos = []): Persona
    {
        return Persona::create(array_merge([
            'cedula' => '12345678',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'Ana Rodríguez Peña',
            'dependencia' => 'Recursos Humanos',
            'activo' => true,
        ], $atributos));
    }

    /** Le anota los dos vehículos: es el caso que la casilla de «qué trae hoy» viene a resolver. */
    private function conCarroYMoto(Persona $persona): Persona
    {
        $persona->vehiculos()->createMany([
            ['tipo' => 'carro', 'marca' => 'Toyota', 'modelo' => 'Corolla', 'color' => 'Gris', 'placa' => 'AB123CD'],
            ['tipo' => 'moto', 'marca' => 'Bera', 'modelo' => 'BR-150', 'color' => 'Negro', 'placa' => 'AC456DF'],
        ]);

        return $persona->fresh();
    }

    public function test_la_pantalla_carga(): void
    {
        $this->get('/marcar')->assertStatus(200)->assertSeeLivewire(Marcar::class);
    }

    public function test_al_teclear_la_cedula_de_un_trabajador_salen_su_nombre_y_su_dependencia(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Ana Rodríguez Peña')
            ->assertSee('Recursos Humanos')
            ->assertSet('invitadoNuevo', false);
    }

    public function test_la_gerencia_del_trabajador_sale_rotulada_y_no_como_un_texto_suelto(): void
    {
        // El vigilante tiene que poder decir de dónde es quien tiene delante sin adivinarlo.
        $this->trabajador(['dependencia' => 'Consultoría Jurídica']);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Gerencia')
            ->assertSee('Consultoría Jurídica');
    }

    public function test_del_trabajador_sale_su_piso_junto_a_la_gerencia(): void
    {
        // Las dos cosas que el vigilante necesita saber de quien labora aquí, juntas.
        $this->trabajador(['dependencia' => 'Tecnología', 'piso' => '2-1']);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Gerencia')
            ->assertSee('Tecnología')
            ->assertSee('Piso')
            ->assertSee('2-1');
    }

    public function test_a_un_invitado_se_le_pregunta_el_piso_y_sin_el_no_se_guarda(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '31415926')
            ->call('buscar')
            ->assertSet('invitadoNuevo', true)
            ->assertSee('¿A qué piso va?')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            // Sin piso no pasa.
            ->call('guardarInvitado')
            ->assertHasErrors('piso')
            ->assertSet('invitadoNuevo', true)
            // Con piso sí.
            ->set('piso', '2-1')
            ->call('guardarInvitado')
            ->assertHasNoErrors()
            ->assertSet('invitadoNuevo', false);

        $this->assertSame('2-1', Persona::where('cedula', '31415926')->sole()->piso);
    }

    public function test_al_invitado_que_vuelve_se_le_vuelve_a_preguntar_el_piso(): void
    {
        // Puede ir a otro sitio que la vez pasada, así que sale el de la última visita pero
        // editable — no se da por sabido.
        $invitado = Persona::create([
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
            'piso' => '2-1',
            'activo' => true,
        ]);

        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->assertSet('piso', '2-1')
            ->set('piso', '4-1')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('movimientos', ['piso' => '4-1']);
        $this->assertSame('4-1', $invitado->fresh()->piso);
    }

    public function test_al_trabajador_tambien_se_le_puede_anotar_el_vehiculo(): void
    {
        // El personal estaciona aquí igual que los invitados. Sin nada anotado todavía, se
        // teclea; y al marcar se le suma a su ficha.
        $persona = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Vehículo')
            ->set('traeHoy', Marcar::OTRO)
            ->set('tipoVehiculo', 'moto')
            ->set('marca', 'Bera')
            ->set('modelo', 'BR-150')
            ->set('color', 'Negro')
            ->set('placa', 'AC456DF')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('movimientos', [
            'tipo_vehiculo' => 'moto',
            'placa' => 'AC456DF',
        ]);

        // Y la próxima vez ya sale en su lista.
        $this->assertSame('AC456DF', $persona->fresh()->vehiculos()->sole()->placa);
    }

    public function test_al_entrar_con_vehiculo_se_le_asigna_el_puesto_elegido(): void
    {
        $this->trabajador();
        $puesto = Puesto::create(['codigo' => 'M-1', 'tipo' => 'moto', 'orden' => 1]);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->set('traeHoy', Marcar::OTRO)
            ->set('tipoVehiculo', 'moto')
            ->set('placa', 'AC456DF')
            ->set('puestoId', (string) $puesto->id)   // se pone DESPUÉS del tipo: cambiar el tipo lo limpia
            ->call('marcarEntrada')
            ->assertHasNoErrors()
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('movimientos', [
            'placa' => 'AC456DF',
            'puesto_id' => $puesto->id,
        ]);
    }

    public function test_quien_tiene_dos_vehiculos_puede_marcar_cual_trae_hoy(): void
    {
        // El caso que motivó todo: Luis tiene carro y moto, y hoy vino en la moto.
        $persona = $this->conCarroYMoto($this->trabajador());

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            // Los dos salen en la casilla, con su placa.
            ->assertSee('AB123CD')
            ->assertSee('AC456DF')
            ->assertSee('¿Qué trae hoy?')
            ->set('traeHoy', 'AC456DF')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('movimientos', [
            'tipo_vehiculo' => 'moto',
            'placa' => 'AC456DF',
            'marca' => 'Bera',
        ]);

        // Señalar uno no se lleva por delante el otro: sigue teniendo los dos.
        $this->assertCount(2, $persona->fresh()->vehiculos);
    }

    public function test_a_quien_tiene_vehiculos_se_le_propone_el_de_la_ultima_entrada(): void
    {
        // Casi siempre viene en el mismo, así que se propone ese y basta con confirmar.
        $this->conCarroYMoto($this->trabajador());

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->set('traeHoy', 'AC456DF')
            ->call('marcarEntrada');

        $this->travel(Marcaje::MINUTOS_ENTRE_SALIDA_Y_ENTRADA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarSalida');

        $this->travel(Marcaje::MINUTOS_ENTRE_SALIDA_Y_ENTRADA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSet('traeHoy', 'AC456DF');
    }

    public function test_de_quien_no_tiene_vehiculos_se_da_por_hecho_que_viene_a_pie(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSet('traeHoy', Marcar::A_PIE)
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('movimientos', ['placa' => null, 'tipo_vehiculo' => null]);
    }

    public function test_quien_tiene_vehiculo_pero_hoy_vino_a_pie_se_marca_a_pie(): void
    {
        $this->conCarroYMoto($this->trabajador());

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Vino a pie')
            ->set('traeHoy', Marcar::A_PIE)
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('movimientos', ['placa' => null]);
    }

    public function test_otro_vehiculo_deja_teclear_uno_que_no_estaba_en_la_lista(): void
    {
        $persona = $this->conCarroYMoto($this->trabajador());

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Otro…')
            ->set('traeHoy', Marcar::OTRO)
            ->set('tipoVehiculo', 'carro')
            ->set('marca', 'Ford')
            ->set('placa', 'AF555LM')
            ->call('marcarEntrada')
            ->assertHasNoErrors()
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('movimientos', [
            'tipo_vehiculo' => 'carro',
            'placa' => 'AF555LM',
        ]);

        // Pasa a tener tres: el nuevo se le suma sin tocar los otros dos.
        $this->assertCount(3, $persona->fresh()->vehiculos);
    }

    public function test_al_teclear_una_placa_suya_con_otra_clase_el_servidor_lo_rechaza(): void
    {
        // La casilla evita el error, pero la pantalla no es el único camino hasta el servicio.
        $this->conCarroYMoto($this->trabajador());

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->set('traeHoy', Marcar::OTRO)
            ->set('tipoVehiculo', 'carro')
            ->set('marca', 'Bera')
            ->set('placa', 'AC456DF')
            ->call('marcarEntrada')
            ->assertHasErrors('tipoVehiculo');

        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_los_ejemplos_del_vehiculo_se_distinguen_de_un_dato_escrito(): void
    {
        // «Toyota» en gris claro se confunde con un Toyota ya escrito. Con «ej.» delante, no.
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            // Las casillas salen al decir que hoy viene en algo, no antes.
            ->call('elegirClase', 'carro')
            ->assertSee('ej. Toyota')
            ->assertSee('ej. Corolla')
            ->assertSee('ej. Gris')
            ->assertSee('ej. AB123CD');
    }

    public function test_a_un_invitado_nuevo_se_le_teclea_el_vehiculo_sin_casilla(): void
    {
        // No tiene ninguno anotado todavía, así que no hay lista que señalar: se teclea.
        Livewire::test(Marcar::class)
            ->set('cedula', '31415926')
            ->call('buscar')
            ->assertSet('invitadoNuevo', true)
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            ->set('piso', '2-1')
            ->set('tipoVehiculo', 'moto')
            ->set('marca', 'Bera')
            ->set('placa', 'AC456DF')
            ->call('guardarInvitado')
            ->assertHasNoErrors();

        $invitado = Persona::where('cedula', '31415926')->sole();

        $this->assertSame('moto', $invitado->vehiculos()->sole()->tipo);
    }

    public function test_quien_entra_caminando_no_queda_con_vehiculo_anotado(): void
    {
        // El botón «Carro» va siempre marcado: no puede colarse en la base por sí solo.
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSet('tipoVehiculo', 'carro')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('movimientos', [
            'tipo_vehiculo' => null,
            'placa' => null,
        ]);
    }

    public function test_a_un_invitado_no_se_le_pregunta_la_gerencia(): void
    {
        // La gerencia es cosa del personal: un invitado no es de ninguna.
        Persona::create([
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
            'activo' => true,
        ]);

        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->assertSee('Carlos Pérez')
            ->assertDontSee('Gerencia');
    }

    public function test_la_cedula_se_puede_teclear_con_puntos(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12.345.678')
            ->call('buscar')
            ->assertSee('Ana Rodríguez Peña');
    }

    public function test_una_cedula_que_no_esta_avisa_que_es_un_invitado_y_pide_dos_datos(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->assertSet('invitadoNuevo', true)
            ->assertSee('es un invitado')
            ->assertSee('Motivo de visita');
    }

    public function test_los_datos_salen_solos_sin_pulsar_enter(): void
    {
        $this->trabajador();

        // Solo se escribe la cédula. No se llama a «buscar»: nadie pulsó nada.
        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->assertSee('Ana Rodríguez Peña')
            ->assertSee('Recursos Humanos');
    }

    public function test_mientras_la_cedula_esta_a_medias_no_se_muestra_nada(): void
    {
        $this->trabajador(['cedula' => '253752'.'58']);

        // Al teclear «25375258» se pasa por estos pasos. Ninguno debe mostrar nada:
        // ni una ficha equivocada, ni el aviso de invitado, ni un error.
        $componente = Livewire::test(Marcar::class);

        foreach (['2', '25', '253', '2537', '25375'] as $aMedias) {
            $componente->set('cedula', $aMedias)
                ->assertSet('invitadoNuevo', false)
                ->assertSet('personaId', null)
                ->assertHasNoErrors()
                ->assertDontSee('es un invitado');
        }
    }

    public function test_al_completar_la_cedula_aparece_la_persona(): void
    {
        $this->trabajador(['cedula' => '25375258', 'nombre' => 'María Fernández']);

        Livewire::test(Marcar::class)
            ->set('cedula', '2537525')      // a medias: nada
            ->assertSet('personaId', null)
            ->set('cedula', '25375258')     // completa: sale
            ->assertSee('María Fernández');
    }

    public function test_una_cedula_desconocida_ya_completa_si_avisa_que_es_un_invitado(): void
    {
        // Sin pulsar Enter: con seis dígitos ya se puede afirmar que no está.
        Livewire::test(Marcar::class)
            ->set('cedula', '876543')
            ->assertSet('invitadoNuevo', true)
            ->assertSee('es un invitado');
    }

    public function test_borrar_la_cedula_deja_la_pantalla_como_al_principio(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->assertSee('Ana Rodríguez Peña')
            ->set('cedula', '')
            ->assertSet('personaId', null)
            ->assertSet('invitadoNuevo', false)
            ->assertHasNoErrors()
            ->assertDontSee('Ana Rodríguez Peña');
    }

    public function test_teclear_no_borra_lo_que_ya_se_escribio_de_la_ficha_del_invitado(): void
    {
        $componente = Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia');

        // Otra búsqueda de la misma cédula desconocida no debe vaciar lo ya escrito.
        $componente->set('cedula', '87654321')
            ->assertSet('nombre', 'Carlos Pérez')
            ->assertset('motivo', 'Videoconferencia');
    }

    public function test_pulsar_enter_sigue_funcionando_para_el_lector_de_carnets(): void
    {
        $this->trabajador();

        // El lector teclea la cédula y termina con un Enter, que es lo que hace «buscar».
        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Ana Rodríguez Peña');
    }

    public function test_el_campo_no_deja_teclear_letras_ni_pasar_del_maximo(): void
    {
        // Se comprueba en el HTML porque esto lo impone el navegador. La regla de verdad la pone
        // el servidor, y eso lo cubre MarcajeTest.
        $html = $this->get('/marcar')->assertOk()->getContent();

        $this->assertStringContainsString('maxlength="'.Marcaje::DIGITOS_MAXIMOS.'"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('replace(/[^0-9]/g', $html);
    }

    public function test_una_cedula_con_mas_digitos_de_los_posibles_no_busca_a_nadie(): void
    {
        $this->trabajador(['cedula' => '12345678']);

        // Aunque el campo lo impida, el componente no se fía: 14 dígitos no son una cédula.
        Livewire::test(Marcar::class)
            ->set('cedula', '12345678901234')
            ->assertSet('personaId', null)
            ->assertSet('invitadoNuevo', false)
            ->assertDontSee('Ana Rodríguez Peña');
    }

    public function test_si_llegan_letras_mezcladas_solo_cuentan_los_digitos(): void
    {
        $this->trabajador(['cedula' => '12345678']);

        // Nadie puede teclear esto en la pantalla, pero si llega, se entiende como la cédula.
        Livewire::test(Marcar::class)
            ->set('cedula', '1a2b3c4d5e6f7g8')
            ->assertSee('Ana Rodríguez Peña');
    }

    public function test_una_cedula_invalida_no_llega_a_buscar_nada(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '123')
            ->call('buscar')
            ->assertHasErrors('cedula')
            ->assertSet('invitadoNuevo', false)
            ->assertSet('personaId', null);
    }

    public function test_marcar_la_entrada_deja_el_movimiento_y_limpia_la_pantalla(): void
    {
        $persona = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada')
            // La pantalla queda lista para el siguiente, sin tocar nada.
            ->assertSet('cedula', '')
            ->assertSet('personaId', null)
            ->assertSee('Entrada registrada')
            ->assertSee('Ana Rodríguez Peña');

        $this->assertDatabaseHas('movimientos', [
            'persona_id' => $persona->id,
            'tipo' => Movimiento::ENTRADA,
        ]);
    }

    /**
     * El invitado que vino a pie la primera vez puede volver en carro, y hay que poder anotarlo.
     *
     * No tiene nada en la ficha —vino caminando—, así que la pantalla no le ofrece lista. Aun así
     * tiene que poder decirse «hoy vino en carro», y lo que se teclee tiene que guardarse.
     */
    public function test_el_invitado_que_vino_a_pie_puede_volver_en_carro(): void
    {
        $invitado = Persona::create([
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
            'piso' => '2-1',
            'activo' => true,
        ]);

        // La primera visita fue a pie: no le quedó ningún vehículo anotado.
        $this->assertSame(0, $invitado->vehiculos()->count());

        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            // Las tres opciones tienen que estar, aunque no tenga nada anotado.
            ->assertSee('Vino a pie')
            ->assertSee('Carro')
            ->assertSee('Moto')
            // Hoy vino en carro: se escoge la clase y se teclea.
            ->call('elegirClase', 'carro')
            ->set('marca', 'Chevrolet')
            ->set('modelo', 'Aveo')
            ->set('color', 'Azul')
            ->set('placa', 'XY987ZW')
            ->call('marcarEntrada')
            ->assertHasNoErrors()
            ->assertSee('Entrada registrada');

        // El asiento lleva el carro, y la ficha se lo queda para la próxima visita.
        $this->assertDatabaseHas('movimientos', ['placa' => 'XY987ZW', 'tipo_vehiculo' => 'carro']);
        $this->assertSame('XY987ZW', $invitado->fresh()->vehiculos()->sole()->placa);
    }

    /**
     * Al invitado que vuelve se le puede anotar OTRO vehículo, y se le suma a su ficha.
     *
     * Un invitado no viene siempre en lo mismo —hoy en su carro, el jueves en el de un amigo—, y
     * la puerta tiene que poder anotarlo sin que nadie entre a arreglar la ficha antes.
     */
    public function test_al_invitado_que_vuelve_se_le_puede_anotar_otro_vehiculo(): void
    {
        $invitado = Persona::create([
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
            'piso' => '2-1',
            'activo' => true,
        ]);

        $invitado->vehiculos()->create([
            'tipo' => 'carro',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'color' => 'Gris',
            'placa' => 'AB123CD',
        ]);

        // Hoy viene en otro: se marca «Otro…» y se teclea.
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->assertSee('Otro…')
            ->set('traeHoy', Marcar::OTRO)
            ->set('tipoVehiculo', 'moto')
            ->set('marca', 'Empire')
            ->set('modelo', 'Horse')
            ->set('color', 'Rojo')
            ->set('placa', 'AE321JK')
            ->call('marcarEntrada')
            ->assertHasNoErrors()
            ->assertSee('Entrada registrada');

        // Le quedan los dos, y la próxima vez los dos salen en la casilla.
        $this->assertSame(
            ['AB123CD', 'AE321JK'],
            $invitado->fresh()->vehiculos->pluck('placa')->sort()->values()->all(),
        );

        $this->assertDatabaseHas('movimientos', ['placa' => 'AE321JK', 'tipo_vehiculo' => 'moto']);
    }

    /**
     * En la salida solo hay dos respuestas posibles, y la pantalla ofrece las dos: irse en lo que
     * entró, o dejarlo estacionado e irse caminando.
     */
    public function test_al_salir_se_puede_dejar_el_vehiculo_estacionado(): void
    {
        $this->conCarroYMoto($this->trabajador());

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->set('traeHoy', 'AB123CD')
            ->call('marcarEntrada');

        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            // Se enseña en qué entró, con todos sus datos, y las dos formas de salir.
            ->assertSee('Toyota Corolla')
            ->assertSee('AB123CD')
            ->assertSee('¿Cómo sale?')
            ->assertSee('A pie, lo deja estacionado')
            // Deja el carro y se va caminando.
            ->set('saleAPie', true)
            ->call('marcarSalida')
            ->assertHasNoErrors()
            ->assertSee('Salida registrada');

        $salida = Movimiento::where('tipo', Movimiento::SALIDA)->latest('id')->sole();

        $this->assertNull($salida->placa);
    }

    /**
     * La pastilla dice la clase, la MARCA, el color y la placa. Todo eso se lee en la pantalla y
     * no en un «title»: se usa en un teléfono, y ahí no hay puntero que pase por encima, así que
     * un title no lo ve nunca nadie.
     *
     * Se comprueban las dos líneas enteras —«Carro Toyota», «Gris · AB123CD»— y no las palabras
     * sueltas: «Toyota» a secas también aparecería si volviera a esconderse en el title, y la
     * prueba pasaría sin que se viera nada en pantalla. Ya pasó una vez.
     */
    public function test_la_pastilla_del_vehiculo_dice_marca_color_y_placa(): void
    {
        $this->conCarroYMoto($this->trabajador());

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Carro Toyota')
            ->assertSee('Gris · AB123CD')
            ->assertSee('Moto Bera')
            ->assertSee('Negro · AC456DF');
    }

    /**
     * Los pisos que se ofrecen como atajo NO son una lista escrita en el código: salen de las
     * fichas que ya hay en la base, así que aparecen solos cuando se cargue el personal de verdad.
     *
     * Y no sustituyen a la casilla: un piso al que todavía no ha ido nadie no puede estar en la
     * lista, y aun así tiene que poder anotarse.
     */
    public function test_el_piso_se_pregunta_primero_y_la_oficina_despues(): void
    {
        // El catálogo del edificio se fija aquí para que la prueba no dependa de cuántas oficinas
        // tenga config/edificio.php el día que se lea.
        config(['edificio.oficinas' => ['LOBBY', '1-2', '2-1', '2-2']]);

        $this->trabajador(['piso' => '2-1', 'dependencia' => 'Tecnología']);
        $this->trabajador(['cedula' => '22222222', 'piso' => '2-2', 'dependencia' => 'Planificación']);
        $this->trabajador(['cedula' => '33333333', 'piso' => '1-2', 'dependencia' => 'Gestión Humana']);

        $pantalla = Livewire::test(Marcar::class)
            // Una cédula que no está: el alta de un invitado, que es donde se pregunta el piso.
            ->set('cedula', '25375258')
            ->call('buscar')
            ->assertSet('invitadoNuevo', true)
            ->assertSee('¿A qué piso va?')
            // Todavía no se ha escogido piso, así que las oficinas no se ofrecen: es lo que evita
            // la lista larga de un edificio entero.
            ->assertDontSee('Tecnología')
            ->assertDontSee('Planificación');

        // Escogido el piso 2, salen sus oficinas con la gerencia de cada una — que es como el
        // visitante dice a dónde va: no pregunta por el «2-1», pregunta por Tecnología.
        $pantalla->call('elegirNivel', '2')
            ->assertSee('¿A qué oficina?')
            ->assertSee('2-1')
            ->assertSee('Tecnología')
            ->assertSee('2-2')
            ->assertSee('Planificación')
            // Las del otro piso siguen sin estorbar.
            ->assertDontSee('Gestión Humana');

        // Y una oficina donde todavía no labora nadie se puede anotar igual, escribiéndola.
        $pantalla->set('nombre', 'Pedro Salazar Ruiz')
            ->set('motivo', 'Videoconferencia')
            ->set('piso', '9-9')
            ->call('guardarInvitado')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', ['cedula' => '25375258', 'piso' => '9-9']);
    }

    /**
     * Un piso con UNA SOLA oficina queda anotado al escoger el piso: preguntar «¿a qué oficina?»
     * para ofrecer una única respuesta es pedir un toque para nada.
     *
     * Se mira cuántas hay, no cómo se llaman: vale igual para «LOBBY» —que es el sitio entero—
     * que para «PB-1» o «8-2», donde el código de la oficina ni siquiera se parece al del piso.
     */
    public function test_un_piso_con_una_sola_oficina_queda_anotado_de_un_toque(): void
    {
        config([
            'edificio.oficinas' => ['LOBBY', 'PB-1', '2-1', '2-2', '7', '9'],
            'edificio.nombres' => ['9' => 'Presidencia'],
        ]);

        $pantalla = Livewire::test(Marcar::class)
            ->set('cedula', '25375258')
            ->call('buscar')
            // Un piso de una sola oficina no llega a enseñar su nombre en la lista de oficinas,
            // porque esa lista no se dibuja. Si el sitio tiene nombre, va en el botón del piso.
            ->assertSee('Presidencia');

        // El sitio entero, sin guion.
        $pantalla->call('elegirNivel', 'LOBBY')
            ->assertSet('piso', 'LOBBY')
            // Ni se pregunta la oficina ni se dice nada más: el botón marcado ya lo dice todo.
            ->assertDontSee('¿A qué oficina?');

        $pantalla->call('elegirNivel', '7')->assertSet('piso', '7');

        // Y la oficina única cuyo código no se parece al del piso: se anota ella, no el piso.
        $pantalla->call('elegirNivel', 'PB')->assertSet('piso', 'PB-1');

        // Un piso con varias sí pregunta, y deja el sitio en blanco hasta que se escoja.
        $pantalla->call('elegirNivel', '2')
            ->assertSet('piso', '')
            ->assertSee('¿A qué oficina?');
    }

    /**
     * Una oficina donde todavía no labora nadie saldría como un código pelado. El catálogo puede
     * ponerle nombre, y ese nombre cede en cuanto haya una ficha que diga otra cosa: la fuente de
     * verdad sigue siendo el personal.
     */
    public function test_el_catalogo_pone_nombre_a_una_oficina_vacia_pero_manda_la_ficha(): void
    {
        config([
            'edificio.oficinas' => ['9', '2-1'],
            'edificio.nombres' => ['9' => 'Presidencia'],
        ]);

        // Se comprueba sobre el mapa y no sobre lo dibujado: dónde se enseñe el nombre es cosa de
        // la maquetación, pero de dónde sale es la regla, y es esta la que no puede cambiar.
        $this->assertSame(
            ['9' => 'Presidencia'],
            Livewire::test(Marcar::class)->instance()->oficinasPorPiso()['9'],
        );

        // Ahora sí hay alguien anotado en esa oficina, y lo que diga su ficha manda.
        $this->trabajador(['cedula' => '44444444', 'piso' => '9', 'dependencia' => 'Despacho']);

        $this->assertSame(
            ['9' => 'Despacho'],
            Livewire::test(Marcar::class)->instance()->oficinasPorPiso()['9'],
        );
    }

    /** El aviso de invitado se puede cerrar, y vuelve a salir con la cédula siguiente. */
    public function test_el_aviso_de_invitado_se_puede_cerrar(): void
    {
        $pantalla = Livewire::test(Marcar::class)
            ->set('cedula', '25375258')
            ->call('buscar')
            ->assertSet('invitadoNuevo', true)
            ->assertSet('avisoInvitado', true)
            ->assertSee('no está en el sistema');

        $pantalla->set('avisoInvitado', false)
            ->assertDontSee('no está en el sistema')
            // Cerrarlo NO cancela el alta: las casillas siguen ahí.
            ->assertSet('invitadoNuevo', true)
            ->assertSee('Nombre y apellido')
            ->assertSee('Motivo de visita');

        // Otra cédula que tampoco está: el aviso vuelve, porque ahora es información.
        $pantalla->set('cedula', '25375259')
            ->assertSet('avisoInvitado', true)
            ->assertSee('no está en el sistema');
    }

    /**
     * El vigilante tiene que poder decir a qué hora quedó lo que acaba de marcar, sin ir al
     * registro a comprobarlo. Vale igual para la entrada que para la salida.
     */
    public function test_la_confirmacion_dice_a_que_hora_quedo_el_movimiento(): void
    {
        $this->travelTo(now()->setTime(9, 6));

        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada a las 9:06 am');

        $this->travelTo(now()->setTime(17, 42));

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarSalida')
            // La tarde en 12 horas: «5:42 pm», como se dice aquí.
            ->assertSee('Salida registrada a las 5:42 pm');
    }

    /**
     * La hora sale del asiento, no del reloj: ante una doble pulsación se devuelve el movimiento
     * que ya existía, y la pantalla tiene que decir la hora de AQUEL. Si dijera la del segundo
     * toque estaría enseñando una hora que no está guardada en ninguna parte.
     */
    public function test_la_doble_pulsacion_confirma_la_hora_del_movimiento_que_ya_existia(): void
    {
        // A cinco segundos de cambiar de minuto, a propósito: es lo que hace que la prueba
        // distinga de verdad la hora del asiento de la del reloj.
        $this->travelTo(now()->setTime(9, 6, 55));

        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada a las 9:06 am');

        // Dentro de la ventana del antiduplicado, pero ya en el minuto siguiente.
        $this->travel(Marcaje::SEGUNDOS_ANTIDUPLICADO - 1)->seconds();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada')
            // Las 9:06 del asiento que ya existía, no las 9:07 que marca el reloj.
            ->assertSee('Entrada registrada a las 9:06 am')
            ->assertDontSee('Entrada registrada a las 9:07 am');

        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_a_quien_ya_esta_dentro_la_pantalla_solo_le_deja_la_salida(): void
    {
        $persona = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada');

        // Recién entrado, la salida todavía no: hay que dejar pasar su plazo.
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('solo se le puede marcar la salida');
    }

    /**
     * Nadie entra y se va al minuto: un par de asientos pegados casi siempre es el carnet leído
     * dos veces o el botón equivocado. Es un plazo distinto del que hay entre dos entradas, y por
     * eso tiene su propia constante.
     */
    public function test_la_salida_no_se_puede_marcar_recien_entrado(): void
    {
        $this->travelTo(now()->setTime(9, 0));

        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada');

        // Un minuto después: está dentro, pero la salida no toca todavía.
        $this->travel(1)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Se le puede marcar la salida')
            ->assertSee('9:05 am')
            ->call('marcarSalida')
            ->assertHasErrors('tipo');

        $this->assertDatabaseCount('movimientos', 1);

        // Cumplido el plazo, sale sin problema.
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarSalida')
            ->assertHasNoErrors()
            ->assertSee('Salida registrada');

        $this->assertDatabaseCount('movimientos', 2);
    }

    public function test_a_quien_ya_esta_dentro_no_se_le_puede_marcar_otra_entrada(): void
    {
        // El botón sale apagado, pero el servidor lo rechaza igual: esconder un botón no es
        // seguridad, y la pantalla no es el único camino hasta el servicio.
        $persona = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        // Pasado el antiduplicado, para que no se confunda con una doble pulsación.
        $this->travel(Marcaje::SEGUNDOS_ANTIDUPLICADO + 5)->seconds();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada')
            ->assertHasErrors('tipo');

        // Sigue habiendo un solo asiento: el segundo no llegó a escribirse.
        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_a_quien_acaba_de_salir_la_pantalla_le_dice_desde_que_hora_puede_entrar(): void
    {
        $persona = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada');

        $this->travel(5)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarSalida')
            ->assertSee('Salida registrada');

        $this->travel(3)->minutes();

        // Ni entrada ni salida: es el único momento en que no se puede marcar nada.
        $componente = Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar');

        $componente
            // Salió hace tres minutos, así que el plazo que manda es el de la salida — y la
            // pantalla tiene que decir ESE motivo, no el de la entrada anterior.
            ->assertSee('Salió hace menos de '.Marcaje::MINUTOS_ENTRE_SALIDA_Y_ENTRADA.' minutos')
            ->assertSee('a partir de las')
            // El botón está apagado, pero el servidor lo rechaza igual.
            ->call('marcarEntrada')
            ->assertHasErrors('tipo');

        $this->assertDatabaseCount('movimientos', 2);
    }

    public function test_cumplida_la_espera_la_pantalla_deja_entrar_otra_vez(): void
    {
        $persona = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada');

        // Lo que haga falta para poder marcarle la salida.
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarSalida');

        // La espera de la ENTRADA se cuenta desde la entrada anterior, no desde esta salida.
        $this->travel(Marcaje::MINUTOS_ENTRE_SALIDA_Y_ENTRADA)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('solo se le puede marcar la entrada')
            ->call('marcarEntrada')
            ->assertHasNoErrors()
            ->assertSee('Entrada registrada');

        $this->assertDatabaseCount('movimientos', 3);
    }

    public function test_a_quien_no_ha_entrado_no_se_le_puede_marcar_la_salida(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('solo se le puede marcar la entrada')
            ->call('marcarSalida')
            ->assertHasErrors('tipo');

        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_el_alta_de_un_invitado_lo_deja_listo_para_marcar_sin_teclear_la_cedula_otra_vez(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            ->set('piso', '2-1')
            ->call('guardarInvitado')
            ->assertSet('invitadoNuevo', false)
            ->assertSee('Carlos Pérez')
            // Ya se puede pulsar el botón: la cédula sigue siendo la misma.
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('personas', [
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
        ]);
    }

    public function test_el_alta_de_un_invitado_guarda_el_vehiculo_en_el_que_llego(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            ->set('piso', '2-1')
            ->set('marca', 'Toyota')
            ->set('modelo', 'Corolla')
            ->set('color', 'Gris')
            ->set('placa', 'ab-123-cd')
            ->call('guardarInvitado')
            ->assertSet('invitadoNuevo', false)
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        // La placa se guarda normalizada aunque se teclee con guiones y en minúsculas.
        $vehiculo = Persona::where('cedula', '87654321')->sole()->vehiculos()->sole();

        $this->assertSame('Toyota', $vehiculo->marca);
        $this->assertSame('Corolla', $vehiculo->modelo);
        $this->assertSame('Gris', $vehiculo->color);
        $this->assertSame('AB123CD', $vehiculo->placa);

        // Y el asiento se lleva su copia del día.
        $this->assertDatabaseHas('movimientos', ['placa' => 'AB123CD']);
    }

    public function test_un_invitado_a_pie_se_guarda_sin_vehiculo(): void
    {
        // El vehículo no es obligatorio: la mayoría de la gente entra caminando.
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            ->set('piso', '2-1')
            ->call('guardarInvitado')
            ->assertHasNoErrors()
            ->assertSet('invitadoNuevo', false);

        $this->assertFalse(Persona::where('cedula', '87654321')->sole()->tieneVehiculos());
    }

    public function test_un_vehiculo_a_medias_avisa_de_que_falta_la_placa(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            ->set('piso', '2-1')
            ->set('marca', 'Toyota')
            ->set('color', 'Gris')
            ->call('guardarInvitado')
            ->assertHasErrors('placa')
            ->assertSet('invitadoNuevo', true);

        $this->assertDatabaseMissing('personas', ['cedula' => '87654321']);
    }

    public function test_al_invitado_que_vuelve_le_sale_su_carro_en_la_casilla(): void
    {
        $invitado = Persona::create([
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
            'activo' => true,
        ]);

        $invitado->vehiculos()->create([
            'tipo' => 'carro',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'color' => 'Gris',
            'placa' => 'AB123CD',
        ]);

        // No hay que volver a preguntárselo: su carro sale en la casilla y solo se señala.
        //
        // Antes se comprobaba «Toyota Corolla», que estaba en el «title» de la pastilla: la prueba
        // pasaba y en el teléfono no se veía nada, porque ahí no hay puntero. Ahora se comprueba
        // lo que de verdad se lee en la pantalla.
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->assertSee('Carro Toyota')
            ->assertSee('Gris · AB123CD')
            ->set('traeHoy', 'AB123CD')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('movimientos', ['placa' => 'AB123CD', 'marca' => 'Toyota']);
    }

    public function test_un_invitado_sin_los_dos_datos_no_se_guarda(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', '')
            ->call('guardarInvitado')
            ->assertHasErrors('motivo')
            ->assertSet('invitadoNuevo', true);

        $this->assertDatabaseMissing('personas', ['cedula' => '87654321']);
    }

    public function test_un_invitado_que_ya_vino_se_marca_solo_con_la_cedula(): void
    {
        // Vino ayer y quedó registrado.
        Persona::create([
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
            'activo' => true,
        ]);

        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            // No vuelve a pedir el formulario: ya sabe quién es.
            ->assertSet('invitadoNuevo', false)
            ->assertSee('Carlos Pérez')
            ->assertset('motivo', 'Videoconferencia')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_a_una_persona_desactivada_la_pantalla_no_la_deja_marcar(): void
    {
        $persona = $this->trabajador(['activo' => false]);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('está desactivada')
            ->call('marcarEntrada')
            ->assertHasErrors('cedula');

        $this->assertDatabaseCount('movimientos', 0);
    }

    /**
     * El contador va separado en trabajadores e invitados: en una emergencia no valen lo mismo,
     * porque a los invitados no los conoce nadie y hay que ir a buscarlos al piso que visitaban.
     */
    public function test_el_contador_separa_a_los_trabajadores_de_los_invitados(): void
    {
        $this->trabajador(['cedula' => '11111111', 'nombre' => 'Ana']);
        $this->trabajador(['cedula' => '22222222', 'nombre' => 'Luis']);

        Livewire::test(Marcar::class)
            ->assertSee('Trabajadores')
            ->assertSee('Invitados');

        // Un trabajador entra…
        Livewire::test(Marcar::class)
            ->set('cedula', '11111111')
            ->call('buscar')
            ->call('marcarEntrada');

        // …y un invitado nuevo también.
        Livewire::test(Marcar::class)
            ->set('cedula', '25375258')
            ->call('buscar')
            ->set('nombre', 'Pedro Salazar Ruiz')
            ->set('motivo', 'Videoconferencia')
            ->set('piso', '2-2')
            ->call('guardarInvitado')
            ->call('marcarEntrada');

        $this->assertSame(
            ['trabajador' => 1, 'invitado' => 1],
            app(Marcaje::class)->cuantosDentroPorTipo(),
        );

        // El total sigue siendo el mismo dato, sumado: la parte 2 lo usa así.
        $this->assertSame(2, app(Marcaje::class)->cuantosDentro());
    }

    public function test_cancelar_devuelve_la_pantalla_a_su_estado_inicial(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('limpiar')
            ->assertSet('cedula', '')
            ->assertSet('personaId', null)
            ->assertSet('invitadoNuevo', false)
            ->assertDontSee('Ana Rodríguez Peña');
    }
}
