<?php

namespace Tests\Feature;

use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A qué llega cada rol. Una pantalla que se abre de más no avisa, así que se escribe aquí.
 *
 * El vigilante es el caso que importa: es la cuenta que está abierta todo el turno, en un teléfono
 * que anda por la puerta. Todo lo que no necesite para su trabajo tiene que estar cerrado para él.
 */
class PermisosDeLasPantallasTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que el VIGILANTE sí puede abrir: su trabajo y nada más. */
    public static function suyas(): array
    {
        return [['marcar'], ['estacionamiento'], ['clave']];
    }

    /** Lo que NO, aunque tenga la sesión abierta. */
    public static function ajenas(): array
    {
        return [
            ['registro'], ['reportes'], ['alertas'], ['visitas'], ['administracion'],
            ['trabajadores'], ['organigrama'], ['usuarios'], ['edificio'], ['puestos'],
            ['pases'], ['rostros'], ['ajustes'], ['auditoria'], ['roles'], ['respaldos'],
            ['asociacion'], ['diseno'], ['maqueta.escaneo'],
        ];
    }

    #[Test]
    #[DataProvider('suyas')]
    public function el_vigilante_abre_lo_suyo(string $ruta): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        $this->get(route($ruta))->assertOk();
    }

    #[Test]
    #[DataProvider('ajenas')]
    public function el_vigilante_no_abre_lo_que_no_es_suyo(string $ruta): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        $this->get(route($ruta))->assertForbidden();
    }

    #[Test]
    public function al_vigilante_la_portada_lo_lleva_a_marcar(): void
    {
        // Su pantalla útil es la puerta: la portada trae el pulso del edificio y accesos a cosas
        // que no puede abrir, así que se le ahorra el rodeo.
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        $this->get(route('inicio'))->assertRedirect(route('marcar'));
    }

    #[Test]
    public function el_supervisor_llega_al_registro_y_a_los_pases_pero_no_a_la_configuracion(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        foreach (['registro', 'reportes', 'alertas', 'visitas', 'usuarios', 'pases'] as $ruta) {
            $this->get(route($ruta))->assertOk("El supervisor debería entrar a «{$ruta}».");
        }

        foreach (['ajustes', 'roles', 'respaldos', 'auditoria', 'trabajadores', 'rostros'] as $ruta) {
            $this->get(route($ruta))->assertForbidden("El supervisor NO debería entrar a «{$ruta}».");
        }
    }

    #[Test]
    public function el_administrador_llega_a_todo(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        foreach (array_merge(self::suyas(), self::ajenas(), [['inicio']]) as [$ruta]) {
            $this->get(route($ruta))->assertOk("El administrador debería entrar a «{$ruta}».");
        }
    }

    #[Test]
    public function sin_sesion_no_se_abre_nada(): void
    {
        foreach (['marcar', 'registro', 'administracion', 'pases', 'rostros'] as $ruta) {
            $this->get(route($ruta))->assertRedirect(route('ingresar'));
        }
    }
}
