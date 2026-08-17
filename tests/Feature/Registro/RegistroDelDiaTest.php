<?php

namespace Tests\Feature\Registro;

use App\Exports\MovimientosDelDia;
use App\Livewire\Registro\RegistroDelDia;
use App\Services\Registro\Ente;
use App\Services\Registro\FuenteDelRegistro;
use App\Services\Registro\Movimiento;
use App\Services\Registro\Persona;
use App\Services\Registro\RegistroInventado;
use App\Services\Registro\TipoDePersona;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistroDelDiaTest extends TestCase
{
    /*
     * Esta pantalla lee de RegistroInventado, que no toca la base, así que antes no hacían falta
     * tablas. Desde que los permisos se guardan en «permisos_de_rol», sí: el gate que abre la
     * pantalla los consulta ahí. Es lo único que cambia; lo que la prueba comprueba, no.
     */
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Desde la parte 3, el registro está detrás del ingreso. Quién puede verlo —el registro
        // es la lista completa del personal, así que el vigilante no— es el bloque B.
        $this->entrandoComo();

        // Estas pruebas comprueban el COMPONENTE (paginación, filtros, panel) contra un juego de
        // datos conocido. La app ya lee de la base real (RegistroReal), pero aquí se fija el
        // inventado a propósito: es el fixture estable con cientos de movimientos que hace falta
        // para probar la paginación. Las pruebas de RegistroReal van aparte, contra la base.
        $this->app->singleton(FuenteDelRegistro::class, RegistroInventado::class);
    }

    private function fuente(): FuenteDelRegistro
    {
        return app(FuenteDelRegistro::class);
    }

    private function primeraPersona(): Persona
    {
        return $this->fuente()->movimientosDelDia(CarbonImmutable::today())->first()->persona;
    }

    #[Test]
    public function la_ruta_del_registro_responde(): void
    {
        $this->get(route('registro'))
            ->assertOk()
            ->assertSee('El registro');
    }

    #[Test]
    public function arranca_en_el_dia_de_hoy(): void
    {
        Livewire::test(RegistroDelDia::class)
            ->assertSet('fecha', CarbonImmutable::today()->toDateString())
            ->assertSet('tipo', '')
            ->assertSet('ente', '')
            ->assertSet('personaEnPanel', null);
    }

    #[Test]
    public function lista_los_movimientos_del_dia(): void
    {
        $primeros = $this->fuente()
            ->movimientosDelDia(CarbonImmutable::today())
            ->take(3);

        $prueba = Livewire::test(RegistroDelDia::class);

        foreach ($primeros as $movimiento) {
            $prueba->assertSee($movimiento->persona->nombre());
            $prueba->assertSee($movimiento->hora());
        }
    }

    #[Test]
    public function filtrar_por_ente_deja_solo_ese_ente(): void
    {
        $hoy = CarbonImmutable::today();
        $soloCiip = $this->fuente()->movimientosDelDia($hoy, null, Ente::Ciip)->count();
        $todos = $this->fuente()->movimientosDelDia($hoy)->count();

        $this->assertLessThan($todos, $soloCiip);

        $componente = Livewire::test(RegistroDelDia::class)
            ->set('ente', 'ciip')
            ->instance();

        $this->assertSame($soloCiip, $componente->movimientos()->total());
    }

    #[Test]
    public function cambiar_de_ente_vuelve_a_la_primera_pagina(): void
    {
        $prueba = Livewire::test(RegistroDelDia::class)->call('setPage', 3);
        $this->assertSame(3, $prueba->instance()->getPage());

        $prueba->set('ente', 'venapp');
        $this->assertSame(1, $prueba->instance()->getPage());
    }

    #[Test]
    public function un_ente_ilegible_en_la_url_no_filtra_nada(): void
    {
        $todos = $this->fuente()->movimientosDelDia(CarbonImmutable::today())->count();

        $componente = Livewire::test(RegistroDelDia::class)
            ->set('ente', 'ministerio-inventado')
            ->instance();

        $this->assertSame($todos, $componente->movimientos()->total());
    }

    #[Test]
    public function el_contador_coincide_con_la_fuente(): void
    {
        $componente = Livewire::test(RegistroDelDia::class)->instance();

        $this->assertSame(
            $this->fuente()->dentroEn(CarbonImmutable::today()),
            $componente->dentro(),
        );
    }

    #[Test]
    public function la_leyenda_del_contador_dice_la_verdad_segun_la_fecha(): void
    {
        // «Dentro ahora» mentiría si se está mirando un día pasado.
        $hoy = Livewire::test(RegistroDelDia::class);
        $this->assertSame('Dentro ahora', $hoy->instance()->leyendaDelContador());

        $pasado = Livewire::test(RegistroDelDia::class)
            ->set('fecha', CarbonImmutable::today()->subDays(2)->toDateString());
        $this->assertSame('Quedaron dentro', $pasado->instance()->leyendaDelContador());
    }

    #[Test]
    public function filtrar_por_invitado_deja_fuera_a_los_trabajadores(): void
    {
        $hoy = CarbonImmutable::today();

        $totalInvitados = $this->fuente()->movimientosDelDia($hoy, TipoDePersona::Invitado)->count();
        $totalTodos = $this->fuente()->movimientosDelDia($hoy)->count();

        $this->assertLessThan($totalTodos, $totalInvitados, 'Los datos de prueba deben tener ambos tipos.');

        $componente = Livewire::test(RegistroDelDia::class)
            ->set('tipo', 'invitado')
            ->instance();

        $this->assertSame($totalInvitados, $componente->movimientos()->total());
    }

    #[Test]
    public function cambiar_de_filtro_vuelve_a_la_primera_pagina(): void
    {
        // Quedarse en la página 3 al cambiar de filtro deja al vigilante mirando una
        // página que casi siempre está vacía.
        $prueba = Livewire::test(RegistroDelDia::class)->call('setPage', 3);
        $this->assertSame(3, $prueba->instance()->getPage());

        $prueba->set('tipo', 'invitado');
        $this->assertSame(1, $prueba->instance()->getPage());
    }

    #[Test]
    public function cambiar_de_fecha_vuelve_a_la_primera_pagina(): void
    {
        $prueba = Livewire::test(RegistroDelDia::class)->call('setPage', 2);
        $this->assertSame(2, $prueba->instance()->getPage());

        $prueba->set('fecha', CarbonImmutable::today()->subDay()->toDateString());
        $this->assertSame(1, $prueba->instance()->getPage());
    }

    #[Test]
    public function se_puede_volver_a_hoy_de_un_toque(): void
    {
        // El botón solo aparece cuando hace falta: mirando hoy no tiene sentido.
        Livewire::test(RegistroDelDia::class)
            ->assertDontSee('Volver a hoy')
            ->set('fecha', CarbonImmutable::today()->subDays(3)->toDateString())
            ->assertSee('Volver a hoy')
            ->call('verHoy')
            ->assertSet('fecha', CarbonImmutable::today()->toDateString())
            ->assertDontSee('Volver a hoy');
    }

    #[Test]
    public function una_fecha_ilegible_no_rompe_la_pantalla_y_se_avisa(): void
    {
        // La fecha viaja en la URL, así que puede llegar cualquier cosa. Caer a hoy está
        // bien; hacerlo en silencio no, porque el vigilante creería estar viendo otro día.
        Livewire::test(RegistroDelDia::class)
            ->set('fecha', 'no-es-una-fecha')
            ->assertOk()
            ->assertSee('No se entiende la fecha')
            ->assertSee('mostrando el día de hoy');
    }

    #[Test]
    public function una_fecha_buena_no_dispara_el_aviso(): void
    {
        Livewire::test(RegistroDelDia::class)
            ->assertDontSee('No se entiende la fecha')
            ->set('fecha', CarbonImmutable::today()->subDay()->toDateString())
            ->assertDontSee('No se entiende la fecha');
    }

    #[Test]
    public function la_pantalla_se_identifica_como_parte_2(): void
    {
        // El color de cada parte identifica el módulo. Si esta pantalla apareciera con el
        // azul de la parte 1 o el verde de la parte 3, el sistema se leería mal.
        $html = $this->get(route('registro'))->getContent();

        $this->assertStringContainsString('bg-parte2-suave', $html);
        $this->assertStringContainsString('text-parte2', $html);
        $this->assertStringNotContainsString('text-parte3', $html);
        $this->assertStringNotContainsString('border-parte1', $html);
    }

    #[Test]
    public function buscar_por_nombre_sugiere_a_esa_persona(): void
    {
        $persona = $this->primeraPersona();

        Livewire::test(RegistroDelDia::class)
            ->set('busqueda', $persona->nombre())
            ->assertSee($persona->nombre());
    }

    #[Test]
    public function buscar_por_documento_sugiere_a_esa_persona(): void
    {
        $persona = $this->fuente()
            ->movimientosDelDia(CarbonImmutable::today())
            ->map(fn (Movimiento $m) => $m->persona)
            ->first(fn (Persona $p) => $p->tieneDocumento());

        Livewire::test(RegistroDelDia::class)
            ->set('busqueda', $persona->documento())
            ->assertSee($persona->nombre());
    }

    #[Test]
    public function buscar_a_alguien_sin_documento_sigue_funcionando_por_nombre(): void
    {
        // Cinco personas del listado real no tienen documento registrado. Si la búsqueda
        // se apoyara solo en la cédula, esa gente sería invisible para el vigilante.
        $sinDocumento = $this->fuente()
            ->movimientosDelDia(CarbonImmutable::today())
            ->map(fn (Movimiento $m) => $m->persona)
            ->first(fn (Persona $p) => ! $p->tieneDocumento());

        if (! $sinDocumento) {
            $this->markTestSkipped('Hoy no se movió nadie sin documento.');
        }

        Livewire::test(RegistroDelDia::class)
            ->set('busqueda', $sinDocumento->nombre())
            ->assertSee($sinDocumento->nombre());
    }

    #[Test]
    public function una_busqueda_sin_resultados_lo_dice(): void
    {
        Livewire::test(RegistroDelDia::class)
            ->set('busqueda', 'zzzzqqqq')
            ->assertSee('Nadie coincide');
    }

    #[Test]
    public function abrir_el_panel_muestra_el_historico_de_la_persona(): void
    {
        $persona = $this->primeraPersona();
        $historico = $this->fuente()->historicoDe($persona->id);

        $prueba = Livewire::test(RegistroDelDia::class)
            ->call('abrirPanel', $persona->id)
            ->assertSet('personaEnPanel', $persona->id)
            ->assertSee('Histórico')
            ->assertSee($persona->documento());

        $this->assertSame($historico->count(), $prueba->instance()->historico()->count());

        // Abrir el panel limpia la búsqueda: ya encontró lo que buscaba.
        $prueba->assertSet('busqueda', '');
    }

    #[Test]
    public function el_panel_muestra_la_adscripcion_y_el_cargo(): void
    {
        // «De a una cédula»: el detalle se ve al consultar a una persona a propósito,
        // nunca volcado en la lista.
        $persona = $this->fuente()
            ->movimientosDelDia(CarbonImmutable::today(), TipoDePersona::Trabajador, Ente::Ciip)
            ->first()
            ->persona;

        Livewire::test(RegistroDelDia::class)
            ->call('abrirPanel', $persona->id)
            ->assertSee($persona->dependencia)
            ->assertSee($persona->cargo)
            ->assertSee($persona->ente->etiqueta());
    }

    #[Test]
    public function el_reporte_conserva_los_campos_crudos_de_una_ficha_mal_cargada(): void
    {
        // La pantalla colapsa el nombre repetido para que se lea; el Excel no, porque es
        // el documento con el que Gestión Humana detecta y corrige la ficha.
        $movimiento = $this->fuente()
            ->movimientosDelDia(CarbonImmutable::today())
            ->first(fn (Movimiento $m) => $m->persona->nombresRepitenApellidos());

        if (! $movimiento) {
            $this->markTestSkipped('Hoy no se movió nadie con la ficha mal cargada.');
        }

        $filas = (new MovimientosDelDia(collect([$movimiento])))->collection();

        $this->assertSame($movimiento->persona->apellidos, $filas->first()[3]);
        $this->assertSame($movimiento->persona->nombres, $filas->first()[4]);
        $this->assertSame($filas->first()[3], $filas->first()[4]);
    }

    #[Test]
    public function cada_fila_ofrece_un_control_accionable_con_el_teclado(): void
    {
        // Antes la fila era un <tr wire:click> pelado: con Tab no se llegaba y con Enter
        // no se abría nada. La única ruta al histórico era el buscador.
        $persona = $this->primeraPersona();

        $html = Livewire::test(RegistroDelDia::class)->html();

        $this->assertStringContainsString(
            'wire:click.stop="abrirPanel(\''.$persona->id.'\')"',
            $html,
        );
        $this->assertStringContainsString('Ver el histórico de', $html);
    }

    #[Test]
    public function abrir_el_historico_desde_el_boton_de_la_fila_funciona(): void
    {
        $persona = $this->primeraPersona();

        Livewire::test(RegistroDelDia::class)
            ->call('abrirPanel', $persona->id)
            ->assertSet('personaEnPanel', $persona->id)
            ->assertSee('Histórico');
    }

    #[Test]
    public function la_tabla_se_anuncia_con_cabeceras_y_titulo(): void
    {
        $html = Livewire::test(RegistroDelDia::class)->html();

        $this->assertStringContainsString('<caption', $html);
        $this->assertStringContainsString('scope="col"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
    }

    #[Test]
    public function exportar_solo_se_deshabilita_por_su_propia_accion(): void
    {
        // Sin wire:target, el botón se apagaba en cada pulsación de cualquier filtro.
        $html = Livewire::test(RegistroDelDia::class)->html();

        $this->assertMatchesRegularExpression(
            '/wire:loading\.attr="disabled"\s+wire:target="exportar"/',
            $html,
        );
    }

    #[Test]
    public function el_estado_de_carga_no_esconde_la_tabla(): void
    {
        // Una `wire:loading` suelta, sin `.class`, significa «muestra esto solo mientras
        // carga»: Livewire le pone display:none al elemento en reposo. Puesta en el
        // contenedor de la tabla, desaparecía la tabla entera y la pantalla quedaba con
        // los filtros y el paginador sobre un hueco vacío.
        //
        // Las pruebas de marcado no lo vieron porque el HTML estaba entero; solo se notaba
        // renderizando en un navegador. De ahí esta comprobación.
        $html = Livewire::test(RegistroDelDia::class)->html();

        preg_match('/<div[^>]*max-h-\[70vh\][^>]*>/', $html, $contenedor);

        $this->assertNotEmpty($contenedor, 'No se encontró el contenedor de la tabla.');
        $this->assertStringContainsString('wire:loading.delay.class="opacity-50"', $contenedor[0]);
        $this->assertDoesNotMatchRegularExpression('/wire:loading(?![.\w])/', $contenedor[0]);
    }

    #[Test]
    public function la_paginacion_sale_en_espanol(): void
    {
        $componente = Livewire::test(RegistroDelDia::class);

        $this->assertTrue(
            $componente->instance()->movimientos()->hasPages(),
            'Hacen falta más de una página para probar el paginador.',
        );

        $componente
            ->assertSee('Siguiente')
            ->assertSee('Mostrando')
            ->assertDontSee('Next')
            ->assertDontSee('Showing');
    }

    #[Test]
    public function el_panel_se_cierra(): void
    {
        $persona = $this->primeraPersona();

        Livewire::test(RegistroDelDia::class)
            ->call('abrirPanel', $persona->id)
            ->call('cerrarPanel')
            ->assertSet('personaEnPanel', null)
            ->assertDontSee('Histórico');
    }

    #[Test]
    public function exportar_descarga_el_reporte_del_dia(): void
    {
        Livewire::test(RegistroDelDia::class)
            ->call('exportar')
            ->assertFileDownloaded('registro-'.CarbonImmutable::today()->toDateString().'.xlsx');
    }

    #[Test]
    public function el_reporte_respeta_los_filtros_de_la_pantalla(): void
    {
        $hoy = CarbonImmutable::today();
        $soloInvitados = $this->fuente()->movimientosDelDia($hoy, TipoDePersona::Invitado);

        $export = new MovimientosDelDia($soloInvitados);
        $filas = $export->collection();

        $this->assertSame($soloInvitados->count(), $filas->count());

        $this->assertSame(
            ['Fecha', 'Hora', 'Documento', 'Apellidos', 'Nombres', 'Ente',
                'Dependencia', 'Tipo', 'Movimiento', 'Registrado por'],
            $export->headings(),
        );

        // Cada fila lleva quién la registró: sin eso el registro no prueba nada.
        $this->assertSame(
            $soloInvitados->first()->registradoPor,
            $filas->first()[9],
        );

        // Apellidos y nombres en columnas separadas, como en el listado de personal.
        $this->assertSame($soloInvitados->first()->persona->apellidos, $filas->first()[3]);
        $this->assertSame($soloInvitados->first()->persona->nombres, $filas->first()[4]);
    }

    #[Test]
    public function el_reporte_no_deja_a_nadie_con_documento_en_blanco_falso(): void
    {
        // Quien no tiene documento registrado sale diciéndolo, no como celda vacía que
        // en la hoja de cálculo se confundiría con un error de exportación.
        $movimientos = $this->fuente()->movimientosDelDia(CarbonImmutable::today());
        $filas = (new MovimientosDelDia($movimientos))->collection();

        foreach ($filas as $fila) {
            $this->assertNotSame('', $fila[2]);
            $this->assertNotSame('V-0', $fila[2]);
        }
    }

    #[Test]
    public function un_dia_sin_movimientos_lo_dice_en_vez_de_mostrar_una_tabla_vacia(): void
    {
        // El domingo el puesto no registra nada.
        $domingo = CarbonImmutable::today();
        while (! $domingo->isSunday()) {
            $domingo = $domingo->subDay();
        }

        Livewire::test(RegistroDelDia::class)
            ->set('fecha', $domingo->toDateString())
            ->assertSee('No hay movimientos con estos filtros');
    }

    #[Test]
    public function la_pantalla_no_permite_editar_ni_borrar_movimientos(): void
    {
        // La regla del módulo: un error se corrige con un movimiento nuevo, nunca
        // tocando el asiento anterior. Si alguien añade una acción de este tipo, que
        // esta prueba lo frene y obligue a discutirlo.
        $metodos = get_class_methods(RegistroDelDia::class);

        foreach (['editar', 'borrar', 'eliminar', 'actualizarMovimiento', 'corregir'] as $prohibido) {
            $this->assertNotContains($prohibido, $metodos);
        }
    }
}
