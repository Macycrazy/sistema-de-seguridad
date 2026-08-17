<?php

namespace Tests\Feature;

use App\Livewire\Marcar;
use App\Models\Movimiento;
use App\Models\Persona;
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
            ->assertSee('Piso')
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

        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADAS)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarSalida');

        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADAS)->minutes();

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
            ->assertSee('A pie')
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
     * Los pisos que se ofrecen como atajo NO son una lista escrita en el código: salen de las
     * fichas que ya hay en la base, así que aparecen solos cuando se cargue el personal de verdad.
     *
     * Y no sustituyen a la casilla: un piso al que todavía no ha ido nadie no puede estar en la
     * lista, y aun así tiene que poder anotarse.
     */
    public function test_los_pisos_que_ya_se_usan_se_ofrecen_como_atajo(): void
    {
        $this->trabajador(['piso' => '2-1']);
        $this->trabajador(['cedula' => '22222222', 'piso' => '1-2']);

        Livewire::test(Marcar::class)
            // Una cédula que no está: el alta de un invitado, que es donde se pregunta el piso.
            ->set('cedula', '25375258')
            ->call('buscar')
            ->assertSet('invitadoNuevo', true)
            ->assertSee('2-1')
            ->assertSee('1-2')
            // Un piso que no usa nadie todavía se puede anotar igual.
            ->set('nombre', 'Pedro Salazar Ruiz')
            ->set('motivo', 'Videoconferencia')
            ->set('piso', '9-9')
            ->call('guardarInvitado')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', ['cedula' => '25375258', 'piso' => '9-9']);
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
            ->assertSee('Entrada registrada a las 09:06');

        $this->travelTo(now()->setTime(17, 42));

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarSalida')
            ->assertSee('Salida registrada a las 17:42');
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
            ->assertSee('Entrada registrada a las 09:06');

        // Dentro de la ventana del antiduplicado, pero ya en el minuto siguiente.
        $this->travel(Marcaje::SEGUNDOS_ANTIDUPLICADO - 1)->seconds();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada')
            // Las 09:06 del asiento que ya existía, no las 09:07 que marca el reloj.
            ->assertSee('Entrada registrada a las 09:06')
            ->assertDontSee('Entrada registrada a las 09:07');

        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_a_quien_ya_esta_dentro_la_pantalla_solo_le_deja_la_salida(): void
    {
        $persona = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada');

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('solo se le puede marcar la salida');
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
            ->assertSee('Entró hace menos de '.Marcaje::MINUTOS_ENTRE_ENTRADAS.' minutos')
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

        $this->travel(2)->minutes();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarSalida');

        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADAS)->minutes();

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
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->assertSee('AB123CD')
            ->assertSee('Toyota Corolla')
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

    public function test_el_contador_de_quienes_estan_dentro_se_ve_en_la_pantalla(): void
    {
        $ana = $this->trabajador(['cedula' => '11111111', 'nombre' => 'Ana']);
        $this->trabajador(['cedula' => '22222222', 'nombre' => 'Luis']);

        Livewire::test(Marcar::class)->assertSee('Dentro ahora');

        Livewire::test(Marcar::class)
            ->set('cedula', '11111111')
            ->call('buscar')
            ->call('marcarEntrada');

        // Con una persona dentro, el contador lo dice.
        $this->assertSame(1, app(Marcaje::class)->cuantosDentro());
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
