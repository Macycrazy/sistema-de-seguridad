<?php

namespace Tests\Feature\Registro;

use App\Models\Movimiento as MovimientoModel;
use App\Models\Persona as PersonaModel;
use App\Models\User;
use App\Services\Marcaje;
use App\Services\Registro\Ente;
use App\Services\Registro\RegistroReal;
use App\Services\Registro\Sentido;
use App\Services\Registro\TipoDePersona;
use App\Usuarios\Rol;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El registro leyendo de las tablas reales. Aquí no hay fixture inventado: se crean personas y
 * movimientos de verdad y se comprueba que RegistroReal los devuelve con la misma forma que
 * esperan la pantalla y las vistas.
 */
class RegistroRealTest extends TestCase
{
    use RefreshDatabase;

    private RegistroReal $fuente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fuente = new RegistroReal;
    }

    private function trabajador(array $atributos = []): PersonaModel
    {
        return PersonaModel::create(array_merge([
            'cedula' => '12345678',
            'tipo' => PersonaModel::TRABAJADOR,
            'nombre' => 'ANA RODRÍGUEZ PEÑA',
            'dependencia' => 'RECURSOS HUMANOS',
            'piso' => '4-1',
            'activo' => true,
        ], $atributos));
    }

    private function anotar(PersonaModel $persona, string $sentido, CarbonImmutable $cuando, ?int $usuarioId = null): MovimientoModel
    {
        return MovimientoModel::create([
            'persona_id' => $persona->id,
            'tipo' => $sentido,
            'ocurrio_en' => $cuando,
            'usuario_id' => $usuarioId,
        ]);
    }

    #[Test]
    public function los_movimientos_del_dia_salen_con_el_mas_reciente_primero(): void
    {
        $ana = $this->trabajador();
        $hoy = CarbonImmutable::today();

        $this->anotar($ana, MovimientoModel::ENTRADA, $hoy->setTime(7, 30));
        $this->anotar($ana, MovimientoModel::SALIDA, $hoy->setTime(16, 15));

        $movimientos = $this->fuente->movimientosDelDia($hoy);

        $this->assertCount(2, $movimientos);
        $this->assertSame(Sentido::Salida, $movimientos->first()->sentido);
        $this->assertSame(Sentido::Entrada, $movimientos->last()->sentido);
        $this->assertSame('ANA RODRÍGUEZ PEÑA', $movimientos->first()->persona->nombre());
    }

    #[Test]
    public function un_movimiento_de_otro_dia_no_aparece_en_el_del_dia(): void
    {
        $ana = $this->trabajador();
        $hoy = CarbonImmutable::today();

        $this->anotar($ana, MovimientoModel::ENTRADA, $hoy->subDay()->setTime(8, 0));

        $this->assertCount(0, $this->fuente->movimientosDelDia($hoy));
        $this->assertCount(1, $this->fuente->movimientosDelDia($hoy->subDay()));
    }

    #[Test]
    public function el_filtro_por_tipo_deja_solo_ese_tipo(): void
    {
        $hoy = CarbonImmutable::today();
        $trabajador = $this->trabajador();
        $invitado = $this->trabajador(['cedula' => '99887766', 'tipo' => PersonaModel::INVITADO, 'nombre' => 'PEDRO SOTO', 'dependencia' => null, 'piso' => null]);

        $this->anotar($trabajador, MovimientoModel::ENTRADA, $hoy->setTime(8, 0));
        $this->anotar($invitado, MovimientoModel::ENTRADA, $hoy->setTime(9, 0));

        $soloInvitados = $this->fuente->movimientosDelDia($hoy, TipoDePersona::Invitado);

        $this->assertCount(1, $soloInvitados);
        $this->assertSame('PEDRO SOTO', $soloInvitados->first()->persona->nombre());
    }

    #[Test]
    public function esta_dentro_quien_entro_y_no_ha_salido(): void
    {
        $hoy = CarbonImmutable::today();
        $dentro = $this->trabajador(['cedula' => '111', 'nombre' => 'UNO']);
        $seFue = $this->trabajador(['cedula' => '222', 'nombre' => 'DOS']);

        $this->anotar($dentro, MovimientoModel::ENTRADA, $hoy->setTime(8, 0));

        $this->anotar($seFue, MovimientoModel::ENTRADA, $hoy->setTime(8, 5));
        $this->anotar($seFue, MovimientoModel::SALIDA, $hoy->setTime(12, 0));

        $this->assertSame(1, $this->fuente->dentroEn($hoy));
    }

    #[Test]
    public function quien_reentra_despues_de_salir_vuelve_a_contar_como_dentro(): void
    {
        $hoy = CarbonImmutable::today();
        $ana = $this->trabajador();

        $this->anotar($ana, MovimientoModel::ENTRADA, $hoy->setTime(8, 0));
        $this->anotar($ana, MovimientoModel::SALIDA, $hoy->setTime(12, 0));
        $this->anotar($ana, MovimientoModel::ENTRADA, $hoy->setTime(13, 0));

        $this->assertSame(1, $this->fuente->dentroEn($hoy));
    }

    #[Test]
    public function la_busqueda_encuentra_por_nombre_sin_acentos_y_por_cedula_con_puntos(): void
    {
        $this->trabajador(['cedula' => '12345678', 'nombre' => 'ANA PÉREZ']);

        $this->assertCount(1, $this->fuente->buscarPersonas('perez'));
        $this->assertCount(1, $this->fuente->buscarPersonas('12.345.678'));
        $this->assertCount(0, $this->fuente->buscarPersonas('zzz'));
    }

    #[Test]
    public function la_busqueda_calla_con_menos_de_dos_letras(): void
    {
        $this->trabajador();

        $this->assertCount(0, $this->fuente->buscarPersonas('a'));
    }

    #[Test]
    public function el_historico_trae_todo_lo_de_una_persona_mas_reciente_primero(): void
    {
        $ana = $this->trabajador();
        $otra = $this->trabajador(['cedula' => '555', 'nombre' => 'OTRA']);
        $hoy = CarbonImmutable::today();

        $this->anotar($ana, MovimientoModel::ENTRADA, $hoy->subDays(2)->setTime(8, 0));
        $this->anotar($ana, MovimientoModel::ENTRADA, $hoy->setTime(8, 0));
        $this->anotar($otra, MovimientoModel::ENTRADA, $hoy->setTime(9, 0));

        $historico = $this->fuente->historicoDe((string) $ana->id);

        $this->assertCount(2, $historico);
        $this->assertTrue($historico->first()->ocurrioEn->greaterThan($historico->last()->ocurrioEn));
    }

    #[Test]
    public function una_persona_real_no_dispara_el_aviso_de_ficha_mal_cargada(): void
    {
        // El nombre real va en un solo campo. No es una ficha con los apellidos repetidos en el
        // campo de nombres, así que el panel no debe mostrar ese aviso.
        $ana = $this->trabajador(['nombre' => 'ANA RODRÍGUEZ PEÑA']);

        $persona = $this->fuente->persona((string) $ana->id);

        $this->assertFalse($persona->nombresRepitenApellidos());
        $this->assertSame('ANA RODRÍGUEZ PEÑA', $persona->nombre());
    }

    #[Test]
    public function el_nombre_no_se_duplica_entre_apellidos_y_nombres(): void
    {
        // El Excel escribe apellidos y nombres en columnas separadas. Con el nombre en un solo
        // campo, va entero en apellidos y nombres queda vacío: nunca repetido en las dos.
        $ana = $this->trabajador(['nombre' => 'ANA RODRÍGUEZ PEÑA']);

        $persona = $this->fuente->persona((string) $ana->id);

        $this->assertSame('ANA RODRÍGUEZ PEÑA', $persona->apellidos);
        $this->assertSame('', $persona->nombres);
    }

    #[Test]
    public function la_cedula_se_muestra_con_puntos_como_en_marcar(): void
    {
        // Consistencia entre pantallas: marcar muestra la cédula con puntos; el registro también.
        $ana = $this->trabajador(['cedula' => '28443995']);

        $this->assertSame('28.443.995', $this->fuente->persona((string) $ana->id)->documento());
        // Y se sigue encontrando, se busque con puntos o sin ellos.
        $this->assertCount(1, $this->fuente->buscarPersonas('28443995'));
        $this->assertCount(1, $this->fuente->buscarPersonas('28.443.995'));
    }

    #[Test]
    public function persona_devuelve_el_value_object_o_null(): void
    {
        $ana = $this->trabajador();

        $this->assertSame('ANA RODRÍGUEZ PEÑA', $this->fuente->persona((string) $ana->id)?->nombre());
        $this->assertNull($this->fuente->persona('999999'));
    }

    #[Test]
    public function registrado_por_muestra_el_nombre_del_usuario(): void
    {
        $vigilante = User::factory()->create(['nombre' => 'Luis Vigía', 'rol' => Rol::VIGILANTE]);
        $ana = $this->trabajador();

        $this->anotar($ana, MovimientoModel::ENTRADA, CarbonImmutable::today()->setTime(8, 0), $vigilante->id);

        $this->assertSame('Luis Vigía', $this->fuente->movimientosDelDia(CarbonImmutable::today())->first()->registradoPor);
    }

    #[Test]
    public function sin_usuario_registrado_por_queda_en_raya(): void
    {
        $ana = $this->trabajador();
        $this->anotar($ana, MovimientoModel::ENTRADA, CarbonImmutable::today()->setTime(8, 0));

        $this->assertSame('—', $this->fuente->movimientosDelDia(CarbonImmutable::today())->first()->registradoPor);
    }

    #[Test]
    public function el_filtro_por_ente_deja_solo_ese_ente(): void
    {
        $hoy = CarbonImmutable::today();
        $ciip = $this->trabajador(['cedula' => '111', 'nombre' => 'DE CIIP', 'ente' => PersonaModel::ENTE_CIIP]);
        $venapp = $this->trabajador(['cedula' => '222', 'nombre' => 'DE VENAPP', 'ente' => PersonaModel::ENTE_VENAPP]);

        $this->anotar($ciip, MovimientoModel::ENTRADA, $hoy->setTime(8, 0));
        $this->anotar($venapp, MovimientoModel::ENTRADA, $hoy->setTime(9, 0));

        $soloCiip = $this->fuente->movimientosDelDia($hoy, null, Ente::Ciip);

        $this->assertCount(1, $soloCiip);
        $this->assertSame('DE CIIP', $soloCiip->first()->persona->nombre());
        $this->assertSame(Ente::Ciip, $soloCiip->first()->persona->ente);
    }

    #[Test]
    public function el_invitado_no_pertenece_a_ningun_ente(): void
    {
        $invitado = $this->trabajador(['cedula' => '333', 'tipo' => PersonaModel::INVITADO, 'nombre' => 'VISITA', 'ente' => null, 'dependencia' => null, 'piso' => null]);

        $this->assertNull($this->fuente->persona((string) $invitado->id)->ente);
    }

    #[Test]
    public function lo_que_marca_la_parte_1_aparece_en_el_registro(): void
    {
        // La prueba de que las dos partes ya se ven: se marca con el servicio real de la puerta
        // y el registro real lo muestra, sin datos inventados de por medio.
        $ana = $this->trabajador();
        $marcaje = app(Marcaje::class);

        $marcaje->registrar($ana, MovimientoModel::ENTRADA);

        $delDia = $this->fuente->movimientosDelDia(CarbonImmutable::today());

        $this->assertCount(1, $delDia);
        $this->assertSame('ANA RODRÍGUEZ PEÑA', $delDia->first()->persona->nombre());
        $this->assertSame(Sentido::Entrada, $delDia->first()->sentido);
        $this->assertSame(1, $this->fuente->dentroEn(CarbonImmutable::today()));
    }
}
