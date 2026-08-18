<?php

namespace Tests\Feature\Auditoria;

use App\Auditoria\Accion;
use App\Livewire\CambiarClave;
use App\Livewire\Ingresar;
use App\Livewire\Usuarios\ListaDeUsuarios;
use App\Models\Auditoria;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\User;
use App\Services\Marcaje;
use App\Services\Rastro;
use App\Usuarios\Rol;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El rastro: que quede anotado lo que tiene que quedar, y solo eso.
 *
 * Es la regla 4 del README —«todo deja rastro»— y el criterio con el que da la parte 3 por
 * terminada: poder responder quién consultó los datos de una persona y en qué momento.
 */
class RastroTest extends TestCase
{
    use RefreshDatabase;

    private function trabajador(string $cedula = '12345678'): Persona
    {
        return Persona::create([
            'cedula' => $cedula,
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'Ana Rodríguez Peña',
            'dependencia' => 'Recursos Humanos',
            'activo' => true,
        ]);
    }

    private function asientos(Accion $accion)
    {
        return Auditoria::where('accion', $accion)->masReciente()->get();
    }

    // ------------------------------------------------------------------ la puerta (parte 1)

    #[Test]
    public function consultar_una_cedula_queda_anotado_con_quien_y_a_quien(): void
    {
        $vigilante = User::factory()->create();
        $this->actingAs($vigilante);
        $persona = $this->trabajador();

        app(Marcaje::class)->buscarPorCedula('12.345.678');

        $asiento = $this->asientos(Accion::CONSULTA_CEDULA)->sole();

        $this->assertSame($vigilante->id, $asiento->usuario_id);
        $this->assertSame($persona->id, $asiento->persona_id);
        $this->assertSame('12345678', $asiento->detalle);
    }

    /** Quien anduvo probando cédulas al azar es justo lo que se quiere poder ver después. */
    #[Test]
    public function consultar_una_cedula_que_no_existe_tambien_queda_anotado(): void
    {
        $this->actingAs(User::factory()->create());

        app(Marcaje::class)->buscarPorCedula('99999999');

        $asiento = $this->asientos(Accion::CONSULTA_CEDULA)->sole();

        $this->assertNull($asiento->persona_id);
        $this->assertSame('99999999', $asiento->detalle);
    }

    /**
     * El campo de la puerta busca en cada pausa del tecleo, así que escribir una cédula dispara
     * varias consultas de lo que para quien mira fue una sola.
     */
    #[Test]
    public function teclear_la_misma_cedula_deja_un_solo_asiento(): void
    {
        $this->actingAs(User::factory()->create());
        $this->trabajador();

        $marcaje = app(Marcaje::class);
        $marcaje->buscarPorCedula('12345678');
        $marcaje->buscarPorCedula('12345678');
        $marcaje->buscarPorCedula('12345678');

        $this->assertCount(1, $this->asientos(Accion::CONSULTA_CEDULA));
    }

    #[Test]
    public function cedulas_distintas_dejan_asientos_distintos(): void
    {
        $this->actingAs(User::factory()->create());

        $marcaje = app(Marcaje::class);
        $marcaje->buscarPorCedula('11111111');
        $marcaje->buscarPorCedula('22222222');

        $this->assertCount(2, $this->asientos(Accion::CONSULTA_CEDULA));
    }

    #[Test]
    public function pasada_la_ventana_la_misma_cedula_vuelve_a_anotarse(): void
    {
        $this->actingAs(User::factory()->create());

        app(Marcaje::class)->buscarPorCedula('12345678');

        $this->travel(Rastro::SEGUNDOS_DE_AGRUPACION + 1)->seconds();

        app(Marcaje::class)->buscarPorCedula('12345678');

        $this->assertCount(2, $this->asientos(Accion::CONSULTA_CEDULA));
    }

    #[Test]
    public function la_agrupacion_es_por_usuario_no_por_cedula(): void
    {
        $primero = User::factory()->create();
        $segundo = User::factory()->create();

        $this->actingAs($primero);
        app(Marcaje::class)->buscarPorCedula('12345678');

        $this->actingAs($segundo);
        app(Marcaje::class)->buscarPorCedula('12345678');

        // Dos personas distintas consultaron la misma cédula: son dos hechos, no uno.
        $this->assertCount(2, $this->asientos(Accion::CONSULTA_CEDULA));
    }

    #[Test]
    public function registrar_un_movimiento_queda_anotado(): void
    {
        $vigilante = User::factory()->create();
        $this->actingAs($vigilante);
        $persona = $this->trabajador();

        app(Marcaje::class)->registrar($persona, Movimiento::ENTRADA, $vigilante->id);

        $asiento = $this->asientos(Accion::MOVIMIENTO_REGISTRADO)->sole();

        $this->assertSame($vigilante->id, $asiento->usuario_id);
        $this->assertSame($persona->id, $asiento->persona_id);
        $this->assertSame(Movimiento::ENTRADA, $asiento->detalle);
    }

    #[Test]
    public function ver_una_foto_queda_anotado_y_un_404_no(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $conFoto = $this->trabajador('11111111');
        $conFoto->update(['foto_ruta' => 'fotos/11111111.jpg']);
        Storage::disk('local')->put('fotos/11111111.jpg', 'contenido');

        $this->get(route('persona.foto', $conFoto))->assertOk();
        $this->get(route('persona.foto', $this->trabajador('22222222')))->assertNotFound();

        // Pedir la foto de quien no tiene no es haber visto ninguna cara.
        $asiento = $this->asientos(Accion::FOTO_VISTA)->sole();
        $this->assertSame($conFoto->id, $asiento->persona_id);
    }

    // ------------------------------------------------------------------ la sesión (parte 3)

    #[Test]
    public function el_ingreso_correcto_y_el_fallido_quedan_anotados(): void
    {
        User::factory()->create(['usuario' => 'vigilante']);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'vigilante')
            ->set('clave', 'la-que-no-es')
            ->call('entrar');

        Livewire::test(Ingresar::class)
            ->set('usuario', 'vigilante')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar');

        $fallido = $this->asientos(Accion::INGRESO_FALLIDO)->sole();
        $this->assertNull($fallido->usuario_id, 'El fallido no sabe todavía quién era.');
        $this->assertSame('vigilante', $fallido->detalle);

        $this->assertCount(1, $this->asientos(Accion::INGRESO_CORRECTO));
    }

    #[Test]
    public function el_intento_de_un_desactivado_queda_anotado_con_su_nombre(): void
    {
        $fuera = User::factory()->desactivado()->create(['usuario' => 'exvigilante']);

        Livewire::test(Ingresar::class)
            ->set('usuario', 'exvigilante')
            ->set('clave', UserFactory::CLAVE)
            ->call('entrar');

        $asiento = $this->asientos(Accion::INGRESO_FALLIDO)->sole();

        // Aquí sí se sabe quién era: dio con la clave buena.
        $this->assertSame($fuera->id, $asiento->usuario_id);
        $this->assertSame('usuario desactivado', $asiento->detalle);
    }

    #[Test]
    public function salir_queda_anotado_a_nombre_de_quien_salio(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('salir'));

        $this->assertSame($usuario->id, $this->asientos(Accion::SALIDA)->sole()->usuario_id);
    }

    #[Test]
    public function cambiar_la_propia_clave_queda_anotado(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        Livewire::test(CambiarClave::class)
            ->set('actual', UserFactory::CLAVE)
            ->set('nueva', 'la-mia-y-de-nadie-mas')
            ->set('repetida', 'la-mia-y-de-nadie-mas')
            ->call('guardar');

        $this->assertSame($usuario->id, $this->asientos(Accion::CLAVE_PROPIA_CAMBIADA)->sole()->usuario_id);
    }

    // ------------------------------------------------------------------ la gestión (parte 3)

    #[Test]
    public function la_gestion_de_usuarios_deja_rastro_de_todo(): void
    {
        $jefa = User::factory()->administrador()->create();
        $this->actingAs($jefa);

        Livewire::test(ListaDeUsuarios::class)
            ->set('usuario', 'jmartinez')
            ->set('nombre', 'José Martínez Rojas')
            ->set('rol', Rol::VIGILANTE->value)
            ->set('clave', 'la-que-yo-quiera')
            ->call('crear');

        $creado = User::where('usuario', 'jmartinez')->sole();

        Livewire::test(ListaDeUsuarios::class)
            ->call('abrirCambioDeClave', $creado->id)
            ->set('claveNueva', 'se-la-dicto-yo')
            ->call('guardarCambioDeClave')
            ->call('abrirCambioDeRol', $creado->id)
            ->set('rolNuevo', Rol::SUPERVISOR->value)
            ->call('guardarCambioDeRol')
            ->call('desactivar', $creado->id)
            ->call('reactivar', $creado->id);

        foreach ([
            Accion::USUARIO_CREADO,
            Accion::USUARIO_CLAVE_CAMBIADA,
            Accion::USUARIO_ROL_CAMBIADO,
            Accion::USUARIO_DESACTIVADO,
            Accion::USUARIO_REACTIVADO,
        ] as $accion) {
            $asiento = $this->asientos($accion)->sole();

            $this->assertSame($jefa->id, $asiento->usuario_id, "«{$accion->value}» sin autor.");
            $this->assertStringContainsString('jmartinez', (string) $asiento->detalle);
        }
    }

    // ------------------------------------------------------------------ el rastro no se toca

    #[Test]
    public function el_modelo_no_lleva_updated_at(): void
    {
        // Tenerlo sería mentir: estos asientos no se actualizan nunca.
        $this->assertNull(Auditoria::UPDATED_AT);
        $this->assertNotContains('updated_at', \Schema::getColumnListing('auditorias'));
    }

    /** Ninguna ruta escribe en el rastro: la auditoría solo se mira. */
    #[Test]
    public function no_hay_ninguna_ruta_que_escriba_el_rastro(): void
    {
        $escriben = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => str_contains($ruta->uri(), 'auditoria'))
            ->reject(fn ($ruta) => $ruta->methods() === ['GET', 'HEAD']);

        $this->assertEmpty($escriben, 'Hay una ruta de auditoría que no es de solo lectura.');
    }
}
