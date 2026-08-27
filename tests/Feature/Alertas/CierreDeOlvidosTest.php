<?php

namespace Tests\Feature\Alertas;

use App\Livewire\Alertas\Panel;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\User;
use App\Services\Alertas\Alerta;
use App\Services\Alertas\Alertas;
use App\Services\Alertas\CierreDeOlvidos;
use App\Services\Auditoria\Auditoria;
use App\Usuarios\Rol;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Las entradas que se quedan abiertas porque nadie marcó la salida.
 *
 * Son dos problemas: el aviso no se apaga nunca —y una pantalla con treinta avisos viejos deja de
 * mirarse— y esa persona sigue contando como «dentro», así que el contador miente.
 */
class CierreDeOlvidosTest extends TestCase
{
    use RefreshDatabase;

    private function entroYNoSalio(string $cedula, string $nombre, string $cuando = '-2 days'): Persona
    {
        $persona = Persona::create([
            'cedula' => $cedula, 'tipo' => Persona::TRABAJADOR, 'nombre' => $nombre, 'activo' => true,
        ]);

        Movimiento::create([
            'persona_id' => $persona->id,
            'tipo' => Movimiento::ENTRADA,
            'ocurrio_en' => CarbonImmutable::now()->sub($cuando === '-2 days' ? '2 days' : $cuando)->setTime(8, 0),
        ]);

        return $persona;
    }

    #[Test]
    public function cerrar_registra_la_salida_que_falto_sin_borrar_nada(): void
    {
        // La regla del registro es que no se edita ni se borra: se añade el movimiento que faltó.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $ana = $this->entroYNoSalio('11111111', 'ANA PÉREZ');

        $salida = app(CierreDeOlvidos::class)->cerrar($ana);

        $this->assertNotNull($salida);
        $this->assertSame(Movimiento::SALIDA, $salida->tipo);
        $this->assertTrue($salida->es_correccion, 'Queda marcada: no fue un marcaje en la puerta.');
        $this->assertSame(2, Movimiento::count(), 'La entrada sigue ahí.');
        $this->assertDatabaseHas('bitacora', ['accion' => Auditoria::CERRO_OLVIDO, 'sobre' => '11111111']);
    }

    #[Test]
    public function la_salida_se_pone_a_la_hora_de_cierre_de_su_dia(): void
    {
        // La hora real no la sabe nadie. Al cierre de SU día el registro queda coherente —entró a
        // las 8, salió a las 18— en vez de una salida dos días después.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $ana = $this->entroYNoSalio('11111111', 'ANA PÉREZ');

        $entrada = Movimiento::first();
        $salida = app(CierreDeOlvidos::class)->cerrar($ana);

        $this->assertSame(CierreDeOlvidos::HORA_DE_CIERRE, $salida->ocurrio_en->hour);
        $this->assertTrue($salida->ocurrio_en->greaterThan($entrada->ocurrio_en), 'Nunca antes de su entrada.');
        $this->assertTrue($salida->ocurrio_en->isSameDay($entrada->ocurrio_en));
    }

    #[Test]
    public function quien_entro_de_noche_no_sale_antes_de_haber_entrado(): void
    {
        // Un turno que empieza a las 22:00: la hora de cierre de ese día ya pasó.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $luis = Persona::create(['cedula' => '22222222', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'LUIS', 'activo' => true]);
        $entrada = Movimiento::create([
            'persona_id' => $luis->id,
            'tipo' => Movimiento::ENTRADA,
            'ocurrio_en' => CarbonImmutable::now()->subDays(2)->setTime(22, 0),
        ]);

        $salida = app(CierreDeOlvidos::class)->cerrar($luis);

        $this->assertTrue($salida->ocurrio_en->greaterThan($entrada->ocurrio_en));
    }

    #[Test]
    public function cerrar_apaga_su_alerta_y_lo_saca_del_contador(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $ana = $this->entroYNoSalio('11111111', 'ANA PÉREZ');

        $this->assertCount(1, app(Alertas::class)->activas()->where('tipo', Alerta::PERMANENCIA));

        app(CierreDeOlvidos::class)->cerrar($ana);

        $this->assertCount(0, app(Alertas::class)->activas()->where('tipo', Alerta::PERMANENCIA));
    }

    #[Test]
    public function a_quien_ya_salio_no_se_le_cierra_dos_veces(): void
    {
        // Dos personas pueden estar limpiando la misma lista a la vez, y eso no es un error.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $ana = $this->entroYNoSalio('11111111', 'ANA PÉREZ');

        app(CierreDeOlvidos::class)->cerrar($ana);

        $this->assertNull(app(CierreDeOlvidos::class)->cerrar($ana));
        $this->assertSame(2, Movimiento::count());
    }

    #[Test]
    public function la_alerta_dice_la_cedula_y_no_solo_el_nombre(): void
    {
        // De estas alertas cuelga cerrarle la salida a alguien, y hay quien se llama parecido —o
        // igual—. El nombre no identifica; la cédula sí.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $this->entroYNoSalio('11111111', 'ANA PÉREZ');

        $alerta = app(Alertas::class)->activas()->firstWhere('tipo', Alerta::PERMANENCIA);

        $this->assertSame('11111111', $alerta->personaCedula);

        Livewire::test(Panel::class)->assertSee('11111111');
    }

    #[Test]
    public function silenciar_calla_el_aviso_sin_tocar_el_registro(): void
    {
        // El guardia de noche SÍ sigue dentro: marcarle una salida sería mentir en el registro.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $ana = $this->entroYNoSalio('11111111', 'ANA PÉREZ');

        app(CierreDeOlvidos::class)->silenciar($ana, 'turno de noche');

        $this->assertCount(0, app(Alertas::class)->activas()->where('tipo', Alerta::PERMANENCIA));
        $this->assertSame(1, Movimiento::count(), 'No se inventa ninguna salida.');
        $this->assertDatabaseHas('bitacora', ['accion' => Auditoria::SILENCIO_ALERTA]);
    }

    #[Test]
    public function el_silencio_caduca_y_el_aviso_vuelve(): void
    {
        // Se calla hasta mañana, no para siempre: si sigue dentro, hay que volver a verlo.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $ana = $this->entroYNoSalio('11111111', 'ANA PÉREZ');

        app(CierreDeOlvidos::class)->silenciar($ana);
        $this->assertCount(0, app(Alertas::class)->activas()->where('tipo', Alerta::PERMANENCIA));

        $this->travel(3)->days();

        $this->assertCount(1, app(Alertas::class)->activas()->where('tipo', Alerta::PERMANENCIA));
    }

    #[Test]
    public function desde_la_pantalla_se_cierran_todas_de_una_vez(): void
    {
        // Con treinta y nueve acumuladas, de una en una no lo hace nadie.
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        $this->entroYNoSalio('11111111', 'ANA PÉREZ');
        $this->entroYNoSalio('22222222', 'LUIS GÓMEZ');
        $this->entroYNoSalio('33333333', 'SARA DÍAZ');

        Livewire::test(Panel::class)
            ->assertSee('Registrar la salida de todas')
            ->call('cerrarTodosLosOlvidos')
            ->assertHasNoErrors();

        $this->assertCount(0, app(Alertas::class)->activas()->where('tipo', Alerta::PERMANENCIA));
        $this->assertSame(3, Movimiento::where('es_correccion', true)->count());
    }
}
