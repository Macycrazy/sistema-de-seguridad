<?php

namespace Tests\Feature;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Marcaje;
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

    public function test_un_invitado_nuevo_se_crea_con_solo_nombre_y_a_quien_visita(): void
    {
        $invitado = $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', 'Ana Rodríguez');

        $this->assertSame(Persona::INVITADO, $invitado->tipo);
        $this->assertSame('87654321', $invitado->cedula);
        $this->assertSame('Carlos Pérez', $invitado->nombre);
        $this->assertSame('Ana Rodríguez', $invitado->visita);

        // Del invitado se guarda lo mínimo: ni dependencia ni foto.
        $this->assertNull($invitado->dependencia);
        $this->assertNull($invitado->foto_ruta);
    }

    public function test_un_invitado_sin_nombre_no_se_guarda(): void
    {
        $this->expectException(ValidationException::class);

        $this->marcaje->registrarInvitado('87654321', '   ', 'Ana Rodríguez');
    }

    public function test_un_invitado_sin_decir_a_quien_visita_no_se_guarda(): void
    {
        $this->expectException(ValidationException::class);

        $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', '  ');
    }

    public function test_no_se_puede_dar_de_alta_un_invitado_con_una_cedula_que_ya_existe(): void
    {
        $this->trabajador(['cedula' => '12345678']);

        $this->expectException(ValidationException::class);

        $this->marcaje->registrarInvitado('12.345.678', 'Carlos Pérez', 'Ana Rodríguez');
    }

    public function test_el_invitado_que_vuelve_se_encuentra_solo_con_la_cedula(): void
    {
        $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', 'Ana Rodríguez');

        $encontrado = $this->marcaje->buscarPorCedula('87654321');

        $this->assertNotNull($encontrado);
        $this->assertSame('Carlos Pérez', $encontrado->nombre);
        $this->assertTrue($encontrado->esInvitado());
    }

    public function test_el_movimiento_de_un_invitado_guarda_a_quien_visitaba_ese_dia(): void
    {
        $invitado = $this->marcaje->registrarInvitado('87654321', 'Carlos Pérez', 'Ana Rodríguez');

        $primero = $this->marcaje->registrar($invitado, Movimiento::ENTRADA);
        $this->assertSame('Ana Rodríguez', $primero->visita);

        // Vuelve otro día a ver a otra persona: el asiento viejo no se toca.
        $segundo = $this->marcaje->registrar($invitado->fresh(), Movimiento::ENTRADA, visita: 'Luis Hernández');

        $this->assertSame('Luis Hernández', $segundo->visita);
        $this->assertSame('Ana Rodríguez', $primero->fresh()->visita);
    }

    public function test_el_movimiento_de_un_trabajador_no_lleva_visita(): void
    {
        $persona = $this->trabajador();

        $movimiento = $this->marcaje->registrar($persona, Movimiento::ENTRADA, visita: 'algo que se ignora');

        $this->assertNull($movimiento->visita);
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
        foreach (['', '123', '12345', '1234567890'] as $invalida) {
            try {
                $this->marcaje->exigirCedulaValida($invalida);
                $this->fail("Aceptó la cédula inválida «{$invalida}»");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
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

        // Vuelve a entrar: cuenta otra vez.
        $this->marcaje->registrar($luis->fresh(), Movimiento::ENTRADA);
        $this->assertSame(3, $this->marcaje->cuantosDentro());
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
