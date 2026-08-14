<?php

namespace Tests\Feature;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Marcaje;
use App\Services\Vehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Las reglas de la puerta. Se prueban contra el servicio, no contra la pantalla, porque es el
 * servidor quien decide: la pantalla solo muestra lo que este servicio le diga.
 */
class MarcajeTest extends TestCase
{
    use RefreshDatabase;

    private Marcaje $marcaje;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marcaje = app(Marcaje::class);
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

    public function test_la_cedula_se_encuentra_aunque_se_teclee_con_puntos_o_con_la_letra(): void
    {
        $this->trabajador(['cedula' => '12345678']);

        foreach (['12345678', '12.345.678', 'V-12.345.678', ' 12345678 '] as $tecleada) {
            $this->assertNotNull(
                $this->marcaje->buscarPorCedula($tecleada),
                "No encontró a la persona tecleando «{$tecleada}»",
            );
        }
    }

    public function test_una_cedula_que_no_esta_en_el_sistema_no_devuelve_a_nadie(): void
    {
        $this->assertNull($this->marcaje->buscarPorCedula('55555555'));
    }

    public function test_a_quien_no_ha_entrado_se_le_propone_la_entrada(): void
    {
        $persona = $this->trabajador();

        $this->assertSame(Movimiento::ENTRADA, $this->marcaje->movimientoSugerido($persona));
    }

    public function test_a_quien_ya_entro_y_no_ha_salido_se_le_propone_la_salida(): void
    {
        $persona = $this->trabajador();
        $this->marcaje->registrar($persona, Movimiento::ENTRADA);

        $this->assertSame(Movimiento::SALIDA, $this->marcaje->movimientoSugerido($persona->fresh()));
    }

    public function test_despues_de_salir_se_vuelve_a_proponer_la_entrada(): void
    {
        $persona = $this->trabajador();
        $this->marcaje->registrar($persona, Movimiento::ENTRADA);
        $this->marcaje->registrar($persona->fresh(), Movimiento::SALIDA);

        $this->assertSame(Movimiento::ENTRADA, $this->marcaje->movimientoSugerido($persona->fresh()));
    }

    public function test_un_movimiento_guarda_la_hora_y_queda_asociado_a_la_persona(): void
    {
        $persona = $this->trabajador();

        $movimiento = $this->marcaje->registrar($persona, Movimiento::ENTRADA);

        $this->assertSame($persona->id, $movimiento->persona_id);
        $this->assertSame(Movimiento::ENTRADA, $movimiento->tipo);
        $this->assertNotNull($movimiento->ocurrio_en);
    }

    public function test_un_invitado_nuevo_se_crea_con_solo_nombre_y_el_motivo(): void
    {
        $invitado = $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', 'Videoconferencia');

        $this->assertSame(Persona::INVITADO, $invitado->tipo);
        $this->assertSame('87654321', $invitado->cedula);
        $this->assertSame('Carlos Pérez', $invitado->nombre);
        $this->assertSame('Videoconferencia', $invitado->motivo);

        // Del invitado se guarda lo mínimo: ni dependencia ni foto.
        $this->assertNull($invitado->dependencia);
        $this->assertNull($invitado->foto_ruta);
    }

    public function test_un_invitado_que_llega_en_carro_lo_deja_anotado(): void
    {
        $invitado = $this->marcaje->registrarInvitado(
            '87654321',
            'Carlos Pérez',
            'Videoconferencia',
            Vehiculo::desde(Vehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', 'AB123CD'),
        );

        $this->assertSame('Toyota', $invitado->marca);
        $this->assertSame('Corolla', $invitado->modelo);
        $this->assertSame('Gris', $invitado->color);
        $this->assertSame('AB123CD', $invitado->placa);
    }

    public function test_un_invitado_que_llega_caminando_no_lleva_vehiculo(): void
    {
        // Es el caso normal, y por eso el vehículo ni siquiera se pasa.
        $invitado = $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', 'Videoconferencia');

        $this->assertNull($invitado->marca);
        $this->assertNull($invitado->modelo);
        $this->assertNull($invitado->color);
        $this->assertNull($invitado->placa);
        $this->assertFalse($invitado->tieneVehiculo());
    }

    public function test_un_vehiculo_sin_la_placa_no_se_guarda(): void
    {
        // «Toyota gris» no identifica ningún carro: hay miles.
        $this->expectException(ValidationException::class);

        $this->marcaje->registrarInvitado(
            '87654321',
            'Carlos Pérez',
            'Videoconferencia',
            Vehiculo::desde(marca: 'Toyota', color: 'Gris'),
        );
    }

    public function test_la_placa_se_guarda_siempre_igual_aunque_se_teclee_de_varias_formas(): void
    {
        // Misma idea que la cédula: si se guarda tal cual se teclea, buscarla luego es una lotería.
        foreach (['AB123CD', 'ab-123-cd', 'AB 123 CD', 'ab123cd'] as $i => $tecleada) {
            $invitado = $this->marcaje->registrarInvitado(
                (string) (87654320 + $i),
                'Carlos Pérez',
                'Videoconferencia',
                Vehiculo::desde(Vehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', $tecleada),
            );

            $this->assertSame('AB123CD', $invitado->placa, "Falló tecleando «{$tecleada}»");
        }
    }

    public function test_un_invitado_sin_nombre_no_se_guarda(): void
    {
        $this->expectException(ValidationException::class);

        $this->marcaje->registrarInvitado('87654321', '   ', 'Videoconferencia');
    }

    public function test_un_invitado_sin_decir_el_motivo_no_se_guarda(): void
    {
        $this->expectException(ValidationException::class);

        $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', '  ');
    }

    public function test_no_se_puede_dar_de_alta_un_invitado_con_una_cedula_que_ya_existe(): void
    {
        $this->trabajador(['cedula' => '12345678']);

        $this->expectException(ValidationException::class);

        $this->marcaje->registrarInvitado('12.345.678', 'Carlos Pérez', 'Videoconferencia');
    }

    public function test_el_invitado_que_vuelve_se_encuentra_solo_con_la_cedula(): void
    {
        $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', 'Videoconferencia');

        $encontrado = $this->marcaje->buscarPorCedula('87654321');

        $this->assertNotNull($encontrado);
        $this->assertSame('Carlos Pérez', $encontrado->nombre);
        $this->assertTrue($encontrado->esInvitado());
    }

    public function test_el_movimiento_de_un_invitado_guarda_el_motivo_de_ese_dia(): void
    {
        $invitado = $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', 'Videoconferencia');

        $primero = $this->marcaje->registrar($invitado, Movimiento::ENTRADA);
        $this->assertSame('Videoconferencia', $primero->motivo);
        $this->marcaje->registrar($invitado->fresh(), Movimiento::SALIDA);

        // Vuelve otro día por otro motivo: el asiento viejo tiene que seguir diciendo el de aquel día.
        $this->travel(1)->day();
        $segundo = $this->marcaje->registrar($invitado->fresh(), Movimiento::ENTRADA, motivo: 'Entrega de material');

        $this->assertSame('Entrega de material', $segundo->motivo);
        $this->assertSame('Videoconferencia', $primero->fresh()->motivo);
    }

    public function test_el_movimiento_de_un_invitado_guarda_el_vehiculo_de_ese_dia(): void
    {
        $invitado = $this->marcaje->registrarInvitado(
            '87654321',
            'Carlos Pérez',
            'Videoconferencia',
            Vehiculo::desde(Vehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', 'AB123CD'),
        );

        $lunes = $this->marcaje->registrar($invitado, Movimiento::ENTRADA);
        $this->assertSame('AB123CD', $lunes->placa);
        $this->assertSame('Toyota', $lunes->marca);
        $this->marcaje->registrar($invitado->fresh(), Movimiento::SALIDA);

        // El jueves viene en otro carro: el asiento del lunes tiene que seguir diciendo el suyo.
        $this->travel(1)->day();
        $jueves = $this->marcaje->registrar(
            $invitado->fresh(),
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(Vehiculo::CARRO, 'Chevrolet', 'Aveo', 'Azul', 'XY987ZW'),
        );

        $this->assertSame('XY987ZW', $jueves->placa);
        $this->assertSame('AB123CD', $lunes->fresh()->placa);
    }

    public function test_el_invitado_que_hoy_viene_caminando_deja_de_tener_carro(): void
    {
        $invitado = $this->marcaje->registrarInvitado(
            '87654321',
            'Carlos Pérez',
            'Videoconferencia',
            Vehiculo::desde(Vehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', 'AB123CD'),
        );

        $enCarro = $this->marcaje->registrar($invitado, Movimiento::ENTRADA);
        $this->marcaje->registrar($invitado->fresh(), Movimiento::SALIDA);

        // Un vehículo VACÍO no es lo mismo que no pasar ninguno: dice «hoy vino sin carro».
        // Sin esta diferencia, la placa del lunes se le quedaría pegada para siempre.
        $this->travel(1)->day();
        $aPie = $this->marcaje->registrar(
            $invitado->fresh(),
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(),
        );

        $this->assertNull($aPie->placa);
        $this->assertFalse($aPie->tieneVehiculo());
        $this->assertFalse($invitado->fresh()->tieneVehiculo());

        // Y el asiento del día que sí trajo carro no se toca.
        $this->assertSame('AB123CD', $enCarro->fresh()->placa);
    }

    public function test_no_pasar_vehiculo_conserva_el_que_ya_tenia_la_ficha(): void
    {
        $invitado = $this->marcaje->registrarInvitado(
            '87654321',
            'Carlos Pérez',
            'Videoconferencia',
            Vehiculo::desde(Vehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', 'AB123CD'),
        );

        $this->marcaje->registrar($invitado, Movimiento::ENTRADA);

        // Nulo significa «no me lo preguntes»: es como marca la salida quien ya entró.
        $salida = $this->marcaje->registrar($invitado->fresh(), Movimiento::SALIDA);

        $this->assertSame('AB123CD', $salida->placa);
        $this->assertSame('AB123CD', $invitado->fresh()->placa);
    }

    public function test_el_trabajador_tambien_puede_llegar_en_vehiculo(): void
    {
        // El personal también estaciona aquí, así que su vehículo se anota igual que el del
        // invitado: en su ficha y congelado en el asiento del día.
        $persona = $this->trabajador();

        $movimiento = $this->marcaje->registrar(
            $persona,
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(Vehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', 'AB123CD'),
        );

        $this->assertSame('AB123CD', $movimiento->placa);
        $this->assertSame(Vehiculo::CARRO, $movimiento->tipo_vehiculo);
        $this->assertSame('AB123CD', $persona->fresh()->placa);
    }

    public function test_el_trabajador_que_llega_en_moto_queda_anotado_como_moto(): void
    {
        $persona = $this->trabajador();

        $movimiento = $this->marcaje->registrar(
            $persona,
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(Vehiculo::MOTO, 'Bera', 'BR-150', 'Negro', 'AC456DF'),
        );

        $this->assertSame(Vehiculo::MOTO, $movimiento->tipo_vehiculo);
        $this->assertTrue($movimiento->vehiculo()->esMoto());
        $this->assertSame('Moto · Bera BR-150 · Negro · AC456DF', $movimiento->vehiculo()->descripcion());
    }

    public function test_una_moto_ya_anotada_no_se_puede_marcar_como_carro(): void
    {
        // Un vehículo no cambia de clase: marcar «carro» sobre la moto de siempre solo puede
        // ser un error de tecleo, y ensuciaría el histórico sin que nadie se entere.
        $persona = $this->trabajador([
            'tipo_vehiculo' => Vehiculo::MOTO,
            'marca' => 'Bera',
            'placa' => 'AC456DF',
        ]);

        $this->expectException(ValidationException::class);

        $this->marcaje->registrar(
            $persona,
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(Vehiculo::CARRO, 'Bera', placa: 'AC456DF'),
        );
    }

    public function test_un_carro_ya_anotado_no_se_puede_marcar_como_moto(): void
    {
        $persona = $this->trabajador([
            'tipo_vehiculo' => Vehiculo::CARRO,
            'marca' => 'Toyota',
            'placa' => 'AB123CD',
        ]);

        $this->expectException(ValidationException::class);

        $this->marcaje->registrar(
            $persona,
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(Vehiculo::MOTO, 'Toyota', placa: 'AB123CD'),
        );
    }

    public function test_con_otra_placa_si_se_puede_elegir_la_clase(): void
    {
        // Porque ya no es el mismo vehículo: hoy llegó en otra cosa, y eso es un dato, no un error.
        $persona = $this->trabajador([
            'tipo_vehiculo' => Vehiculo::MOTO,
            'marca' => 'Bera',
            'placa' => 'AC456DF',
        ]);

        $movimiento = $this->marcaje->registrar(
            $persona,
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(Vehiculo::CARRO, 'Toyota', 'Corolla', 'Gris', 'AB123CD'),
        );

        $this->assertSame(Vehiculo::CARRO, $movimiento->tipo_vehiculo);
        $this->assertSame('AB123CD', $movimiento->placa);
        $this->assertSame(Vehiculo::CARRO, $persona->fresh()->tipo_vehiculo);
    }

    public function test_repetir_el_mismo_vehiculo_tal_cual_no_da_problema(): void
    {
        $persona = $this->trabajador([
            'tipo_vehiculo' => Vehiculo::MOTO,
            'marca' => 'Bera',
            'placa' => 'AC456DF',
        ]);

        $movimiento = $this->marcaje->registrar(
            $persona,
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(Vehiculo::MOTO, 'Bera', placa: 'AC456DF'),
        );

        $this->assertSame(Vehiculo::MOTO, $movimiento->tipo_vehiculo);
    }

    public function test_quien_hoy_viene_caminando_no_choca_con_la_clase_de_su_vehiculo(): void
    {
        // Vaciar el vehículo no es cambiarle la clase: es decir que hoy no trajo ninguno.
        $persona = $this->trabajador([
            'tipo_vehiculo' => Vehiculo::MOTO,
            'marca' => 'Bera',
            'placa' => 'AC456DF',
        ]);

        $movimiento = $this->marcaje->registrar(
            $persona,
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(Vehiculo::CARRO),
        );

        $this->assertNull($movimiento->tipo_vehiculo);
        $this->assertFalse($persona->fresh()->tieneVehiculo());
    }

    public function test_el_asiento_de_quien_entra_caminando_no_guarda_ningun_tipo(): void
    {
        // El botón «Carro» va siempre marcado en la pantalla; si no hay vehículo, no debe
        // colarse ese tipo en la base y dejar a alguien a pie con carro anotado.
        $persona = $this->trabajador();

        $movimiento = $this->marcaje->registrar(
            $persona,
            Movimiento::ENTRADA,
            vehiculo: Vehiculo::desde(Vehiculo::CARRO),
        );

        $this->assertNull($movimiento->tipo_vehiculo);
        $this->assertNull($movimiento->placa);
        $this->assertFalse($movimiento->tieneVehiculo());
    }

    public function test_el_movimiento_de_un_trabajador_no_lleva_motivo(): void
    {
        $persona = $this->trabajador();

        $movimiento = $this->marcaje->registrar($persona, Movimiento::ENTRADA, motivo: 'algo que se ignora');

        $this->assertNull($movimiento->motivo);
    }

    public function test_a_una_persona_desactivada_no_se_le_puede_marcar(): void
    {
        $persona = $this->trabajador(['activo' => false]);

        $this->expectException(ValidationException::class);

        $this->marcaje->registrar($persona, Movimiento::ENTRADA);
    }

    public function test_un_movimiento_que_no_sea_entrada_ni_salida_se_rechaza(): void
    {
        $persona = $this->trabajador();

        $this->expectException(ValidationException::class);

        $this->marcaje->registrar($persona, 'cualquier_cosa');
    }

    public function test_una_cedula_demasiado_corta_o_larga_se_rechaza(): void
    {
        foreach (['', '123', '12345', '1234567890', '12345678901234'] as $invalida) {
            try {
                $this->marcaje->exigirCedulaValida($invalida);
                $this->fail("Aceptó la cédula inválida «{$invalida}»");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_una_cedula_de_puras_letras_se_rechaza(): void
    {
        // La pantalla ya no deja teclear letras, pero eso es comodidad, no seguridad: quien
        // envie una peticion a mano se topa igual con el servidor.
        foreach (['abcdefgh', 'V-ABCDEFG', '????????'] as $invalida) {
            try {
                $this->marcaje->exigirCedulaValida($invalida);
                $this->fail("Aceptó «{$invalida}» como cédula");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_las_letras_mezcladas_no_cuentan_como_digitos(): void
    {
        // «12a34b56» tiene ocho caracteres pero solo seis dígitos: es la cédula 123456.
        $this->assertSame('123456', $this->marcaje->exigirCedulaValida('12a34b56'));
    }

    public function test_el_contador_de_quien_esta_dentro_cuenta_solo_a_los_que_no_han_salido(): void
    {
        $ana = $this->trabajador(['cedula' => '11111111', 'nombre' => 'Ana']);
        $luis = $this->trabajador(['cedula' => '22222222', 'nombre' => 'Luis']);
        $rosa = $this->trabajador(['cedula' => '33333333', 'nombre' => 'Rosa']);

        $this->assertSame(0, $this->marcaje->cuantosDentro());

        $this->marcaje->registrar($ana, Movimiento::ENTRADA);
        $this->marcaje->registrar($luis, Movimiento::ENTRADA);
        $this->marcaje->registrar($rosa, Movimiento::ENTRADA);
        $this->assertSame(3, $this->marcaje->cuantosDentro());

        $this->marcaje->registrar($luis->fresh(), Movimiento::SALIDA);
        $this->assertSame(2, $this->marcaje->cuantosDentro());

        // Vuelve a entrar: cuenta otra vez. Hay que dejar pasar la espera entre entradas,
        // que aquí no es lo que se está probando pero se aplica igual.
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADAS)->minutes();
        $this->marcaje->registrar($luis->fresh(), Movimiento::ENTRADA);
        $this->assertSame(3, $this->marcaje->cuantosDentro());
    }

    public function test_una_doble_pulsacion_no_deja_dos_movimientos(): void
    {
        $persona = $this->trabajador();

        $primero = $this->marcaje->registrar($persona, Movimiento::ENTRADA);
        $segundo = $this->marcaje->registrar($persona->fresh(), Movimiento::ENTRADA);

        // Es el mismo asiento devuelto otra vez, no uno nuevo.
        $this->assertSame($primero->id, $segundo->id);
        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_el_antiduplicado_no_estorba_a_una_correccion(): void
    {
        $persona = $this->trabajador();

        // Se marcó una entrada por error y se corrige en el acto con una salida.
        $this->marcaje->registrar($persona, Movimiento::ENTRADA);
        $this->marcaje->registrar($persona->fresh(), Movimiento::SALIDA);

        // Los dos quedan: el tipo es distinto, así que no es una repetición.
        $this->assertDatabaseCount('movimientos', 2);
    }

    public function test_pasada_la_ventana_tampoco_se_entra_dos_veces(): void
    {
        $persona = $this->trabajador();

        $this->marcaje->registrar($persona, Movimiento::ENTRADA);

        // Fuera de la ventana del antiduplicado ya no es una doble pulsación, pero sigue sin
        // tener sentido: quien está dentro no vuelve a entrar. Antes esto creaba un segundo
        // asiento y quedaba en el histórico para siempre; ahora se rechaza.
        $this->travel(Marcaje::SEGUNDOS_ANTIDUPLICADO + 5)->seconds();

        try {
            $this->marcaje->registrar($persona->fresh(), Movimiento::ENTRADA);
            $this->fail('Debió rechazar la segunda entrada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tipo', $e->errors());
        }

        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_no_se_puede_salir_sin_haber_entrado(): void
    {
        $persona = $this->trabajador();

        $this->expectException(ValidationException::class);

        $this->marcaje->registrar($persona, Movimiento::SALIDA);
    }

    public function test_el_vaiven_normal_de_un_dia_pasa_sin_estorbo(): void
    {
        // Entrar, salir y volver a entrar es lo corriente: la regla no puede estorbarlo,
        // siempre que entre las dos entradas hayan pasado los minutos de rigor.
        $persona = $this->trabajador();

        $this->marcaje->registrar($persona, Movimiento::ENTRADA);
        $this->travel(Marcaje::SEGUNDOS_ANTIDUPLICADO + 5)->seconds();
        $this->marcaje->registrar($persona->fresh(), Movimiento::SALIDA);
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADAS)->minutes();
        $this->marcaje->registrar($persona->fresh(), Movimiento::ENTRADA);

        $this->assertDatabaseCount('movimientos', 3);
        $this->assertTrue($persona->fresh()->estaDentro());
    }

    public function test_quien_acaba_de_salir_no_puede_volver_a_entrar_en_seguida(): void
    {
        // Entra, sale a los cinco minutos y quiere volver: todavía no.
        $persona = $this->trabajador();

        $this->marcaje->registrar($persona, Movimiento::ENTRADA);
        $this->travel(5)->minutes();
        $this->marcaje->registrar($persona->fresh(), Movimiento::SALIDA);
        $this->travel(3)->minutes();

        try {
            $this->marcaje->registrar($persona->fresh(), Movimiento::ENTRADA);
            $this->fail('Debió rechazar la entrada: no han pasado los minutos de espera.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tipo', $e->errors());
        }

        // Los dos movimientos de antes siguen ahí; el tercero no llegó a escribirse.
        $this->assertDatabaseCount('movimientos', 2);
    }

    public function test_la_espera_se_cuenta_desde_la_entrada_y_no_desde_la_salida(): void
    {
        // Es la clave de la regla: si se contara desde la salida, entrar y salir a cada rato
        // seguiría llenando el histórico — bastaría con quedarse un minuto adentro.
        $persona = $this->trabajador();

        $this->marcaje->registrar($persona, Movimiento::ENTRADA);
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADAS - 2)->minutes();
        $this->marcaje->registrar($persona->fresh(), Movimiento::SALIDA);

        // Han pasado 18 minutos desde la entrada y 0 desde la salida.
        $this->travel(2)->minutes();
        $this->marcaje->registrar($persona->fresh(), Movimiento::ENTRADA);

        $this->assertDatabaseCount('movimientos', 3);
    }

    public function test_la_pantalla_puede_saber_a_que_hora_podra_entrar(): void
    {
        $persona = $this->trabajador();

        // Sin movimientos, nada que esperar.
        $this->assertNull($this->marcaje->puedeEntrarDesde($persona));

        $entrada = $this->marcaje->registrar($persona, Movimiento::ENTRADA);
        $this->travel(5)->minutes();
        $this->marcaje->registrar($persona->fresh(), Movimiento::SALIDA);

        $desde = $this->marcaje->puedeEntrarDesde($persona->fresh());

        $this->assertNotNull($desde);
        $this->assertSame(
            $entrada->ocurrio_en->copy()->addMinutes(Marcaje::MINUTOS_ENTRE_ENTRADAS)->format('H:i'),
            $desde->format('H:i'),
        );

        // Cumplido el plazo, ya no hay nada que esperar.
        $this->travel(Marcaje::MINUTOS_ENTRE_ENTRADAS)->minutes();
        $this->assertNull($this->marcaje->puedeEntrarDesde($persona->fresh()));
    }

    public function test_la_espera_no_estorba_a_la_salida(): void
    {
        // Quien está dentro puede salir cuando quiera: la espera es solo para entrar.
        $persona = $this->trabajador();

        $this->marcaje->registrar($persona, Movimiento::ENTRADA);
        $this->travel(Marcaje::SEGUNDOS_ANTIDUPLICADO + 5)->seconds();
        $this->marcaje->registrar($persona->fresh(), Movimiento::SALIDA);

        $this->assertDatabaseCount('movimientos', 2);
    }

    public function test_la_espera_vale_igual_para_un_invitado(): void
    {
        $invitado = $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', 'Videoconferencia');

        $this->marcaje->registrar($invitado, Movimiento::ENTRADA);
        $this->travel(2)->minutes();
        $this->marcaje->registrar($invitado->fresh(), Movimiento::SALIDA);
        $this->travel(2)->minutes();

        $this->expectException(ValidationException::class);

        $this->marcaje->registrar($invitado->fresh(), Movimiento::ENTRADA);
    }

    public function test_la_doble_pulsacion_no_le_saca_un_aviso_al_vigilante(): void
    {
        // La comprobación va DESPUÉS del antiduplicado a propósito: pulsar dos veces el botón
        // no es un error del vigilante y no debe sacarle un mensaje rojo en pantalla.
        $persona = $this->trabajador();

        $primero = $this->marcaje->registrar($persona, Movimiento::ENTRADA);
        $segundo = $this->marcaje->registrar($persona->fresh(), Movimiento::ENTRADA);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_la_doble_pulsacion_de_dos_personas_distintas_no_se_confunde(): void
    {
        $ana = $this->trabajador(['cedula' => '11111111', 'nombre' => 'Ana']);
        $luis = $this->trabajador(['cedula' => '22222222', 'nombre' => 'Luis']);

        // Dos personas seguidas, la misma acción y en el mismo segundo: son dos asientos.
        $this->marcaje->registrar($ana, Movimiento::ENTRADA);
        $this->marcaje->registrar($luis, Movimiento::ENTRADA);

        $this->assertDatabaseCount('movimientos', 2);
        $this->assertSame(2, $this->marcaje->cuantosDentro());
    }

    public function test_un_movimiento_no_se_puede_borrar_si_arrastra_a_la_persona(): void
    {
        $persona = $this->trabajador();
        $this->marcaje->registrar($persona, Movimiento::ENTRADA);

        // La ficha de alguien con movimientos no se borra: el histórico de la puerta manda.
        $this->expectException(QueryException::class);

        $persona->delete();
    }
}
