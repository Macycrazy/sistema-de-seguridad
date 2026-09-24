<?php

namespace Tests\Feature\Carnets;

use App\Livewire\Trabajadores\ListaDeTrabajadores;
use App\Models\Persona;
use App\Models\User;
use App\Services\Carnets\CotejoConCarnets;
use App\Services\GestionDeTrabajadores;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El cotejo entre las dos listas de personal.
 *
 * Se llevan por separado y se separan solas: entra alguien, lo dan de alta en carnets, aquí nadie
 * lo carga, y el día que llega no aparece en la puerta.
 */
class CotejoConCarnetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['carnets.token' => 'un-token', 'carnets.url' => 'https://carnet.example']);
    }

    /**
     * El carnets respondiendo. Por omisión todos «Activo», que es el caso normal; un estatus
     * distinto es lo que marca una baja allá.
     *
     * @param  array<int, array{cedula:string, nombre:string, gerencia?:string, estatus?:string}>  $fichas
     */
    private function carnetsResponde(array $fichas): void
    {
        Http::fake([
            '*/api/seguridad/personal*' => Http::response([
                'total' => count($fichas),
                'personal' => array_map(fn ($f) => [
                    'cedula' => $f['cedula'],
                    'nombre_completo' => $f['nombre'],
                    'gerencia' => $f['gerencia'] ?? 'GERENCIA A',
                    'estatus' => $f['estatus'] ?? 'Activo',
                ], $fichas),
            ]),
        ]);
    }

    private function aqui(string $cedula, string $nombre, bool $activo = true, ?string $ente = 'ciip'): Persona
    {
        return Persona::create([
            'cedula' => $cedula,
            'tipo' => Persona::TRABAJADOR,
            'nombre' => $nombre,
            'ente' => $ente,
            'activo' => $activo,
        ]);
    }

    #[Test]
    public function saca_a_quien_esta_en_carnets_y_no_aqui(): void
    {
        // El caso que importa: esa persona se planta en la puerta y no aparece.
        $this->aqui('11111111', 'ANA PÉREZ');

        $this->carnetsResponde([
            ['cedula' => '11111111', 'nombre' => 'ANA PÉREZ'],
            ['cedula' => '22222222', 'nombre' => 'LUIS GÓMEZ'],
        ]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertTrue($resultado['disponible']);
        $this->assertCount(1, $resultado['faltan']);
        $this->assertSame('22222222', $resultado['faltan'][0]['cedula']);
        $this->assertSame(1, $resultado['coinciden']);
    }

    #[Test]
    public function saca_a_quien_no_aparece_en_carnets_pero_sin_tocarlo(): void
    {
        // Ni activo ni de baja: no consta. Puede ser una baja vieja o un dato mal cargado, así que
        // solo se dice —a diferencia de quien SÍ consta de baja, que se puede desactivar—.
        $this->aqui('11111111', 'ANA PÉREZ');
        $this->aqui('33333333', 'QUIEN SE FUE');   // del CIIP: de ese sí se puede decir algo

        $this->carnetsResponde([['cedula' => '11111111', 'nombre' => 'ANA PÉREZ']]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertCount(1, $resultado['sobran']);
        $this->assertSame('33333333', $resultado['sobran'][0]->cedula);
    }

    #[Test]
    public function quien_esta_desactivado_aqui_no_falta_sino_que_hay_que_reactivarlo(): void
    {
        // Tampoco puede marcar, que es el problema. Pero su ficha existe con su histórico: crearla
        // otra vez encima pisaría su piso, su ente y su dependencia con lo que diga el carnets.
        $this->aqui('11111111', 'ANA PÉREZ', activo: false);

        $this->carnetsResponde([['cedula' => '11111111', 'nombre' => 'ANA PÉREZ']]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertCount(0, $resultado['faltan'], 'No hay que crearla: ya está.');
        $this->assertCount(1, $resultado['desactivados']);
        $this->assertSame('11111111', $resultado['desactivados'][0]->cedula);
    }

    #[Test]
    public function reactivar_desde_la_pantalla_conserva_su_ficha(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $ana = $this->aqui('11111111', 'ANA PÉREZ', activo: false);
        $ana->update(['piso' => '4-1', 'dependencia' => 'LO QUE TENÍA']);

        $this->carnetsResponde([['cedula' => '11111111', 'nombre' => 'ANA PÉREZ', 'gerencia' => 'OTRA COSA']]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->assertSee('desactivados aquí, activos en carnets')
            ->call('reactivarDelPadron', '11111111')
            ->assertHasNoErrors();

        $ana->refresh();

        $this->assertTrue((bool) $ana->activo);
        $this->assertSame('4-1', $ana->piso, 'Reactivar no pisa lo que ya tenía.');
        $this->assertSame('LO QUE TENÍA', $ana->dependencia);
    }

    #[Test]
    public function quien_esta_de_baja_en_carnets_y_activo_aqui_sale_para_igualarlo(): void
    {
        // Lo que se busca: que el estado sea el mismo en los dos sitios. De este no hay duda —el
        // carnets dice su baja con todas las letras— así que se puede desactivar con confianza.
        $this->aqui('11111111', 'ANA PÉREZ');
        $this->aqui('33333333', 'QUIEN SE FUE');

        $this->carnetsResponde([
            ['cedula' => '11111111', 'nombre' => 'ANA PÉREZ'],
            ['cedula' => '33333333', 'nombre' => 'QUIEN SE FUE', 'estatus' => 'Inactivo'],
        ]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertCount(1, $resultado['inactivosEnCarnets']);
        $this->assertSame('33333333', $resultado['inactivosEnCarnets'][0]['persona']->cedula);
        $this->assertSame('Inactivo', $resultado['inactivosEnCarnets'][0]['estatus']);

        // Y no se mezcla con «no aparece en carnets», que es otra cosa: de este sí consta.
        $this->assertCount(0, $resultado['sobran']);
    }

    #[Test]
    public function desactivar_desde_la_pantalla_iguala_el_estado_y_conserva_el_historico(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $quienSeFue = $this->aqui('33333333', 'QUIEN SE FUE');

        $this->carnetsResponde([
            ['cedula' => '33333333', 'nombre' => 'QUIEN SE FUE', 'estatus' => 'Retirado'],
        ]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->assertSee('de baja en carnets')
            ->assertSee('Retirado')
            ->call('desactivarComoEnCarnets', '33333333')
            ->assertHasNoErrors();

        $this->assertFalse((bool) $quienSeFue->fresh()->activo);
        $this->assertNotNull($quienSeFue->fresh(), 'Se desactiva, no se borra: el histórico se conserva.');
    }

    #[Test]
    public function se_pueden_igualar_todos_de_una_vez(): void
    {
        // En bloque solo para esto: el carnets dice explícitamente que están de baja, y
        // desactivar no borra nada ni impide volver atrás.
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $this->aqui('33333333', 'UNO');
        $this->aqui('44444444', 'OTRO');
        $this->aqui('11111111', 'ANA PÉREZ');

        $this->carnetsResponde([
            ['cedula' => '33333333', 'nombre' => 'UNO', 'estatus' => 'Inactivo'],
            ['cedula' => '44444444', 'nombre' => 'OTRO', 'estatus' => 'Inactivo'],
            ['cedula' => '11111111', 'nombre' => 'ANA PÉREZ'],
        ]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->call('desactivarTodosComoEnCarnets')
            ->assertHasNoErrors();

        $this->assertSame(1, Persona::where('activo', true)->count(), 'Solo queda activa Ana.');
    }

    #[Test]
    public function el_personal_de_marca_pais_y_venapp_nunca_sobra(): void
    {
        // El carnets es SOLO del CIIP: de los otros dos entes no está nadie allá, y por diseño.
        // Contarlos como sobrantes llenaría la pantalla de avisos falsos.
        $this->aqui('11111111', 'ANA PÉREZ');
        $this->aqui('55555555', 'PEDRO DE VENAPP', ente: 'venapp');
        $this->aqui('66666666', 'SARA DE MARCA PAÍS', ente: 'marca-pais');

        $this->carnetsResponde([['cedula' => '11111111', 'nombre' => 'ANA PÉREZ']]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertCount(0, $resultado['sobran'], 'No son del CIIP: no tienen por qué tener carnet.');
        $this->assertSame(2, $resultado['otrosEntes']);
    }

    #[Test]
    public function quien_no_tiene_ente_se_lista_aparte_sin_acusarlo(): void
    {
        // No se puede saber si le falta el carnet o es que no es del CIIP. Meterlo en «sobran»
        // sería afirmar lo primero sin base.
        $this->aqui('77777777', 'SIN ENTE', ente: null);

        $this->carnetsResponde([['cedula' => '11111111', 'nombre' => 'OTRA']]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertCount(0, $resultado['sobran']);
        $this->assertCount(1, $resultado['sinEnte']);
        $this->assertSame('77777777', $resultado['sinEnte'][0]->cedula);
    }

    #[Test]
    public function los_visitantes_no_entran_en_el_cotejo(): void
    {
        // El carnets es del personal: un visitante de aquí no tiene por qué estar allá.
        Persona::create([
            'cedula' => '44444444', 'tipo' => Persona::INVITADO,
            'nombre' => 'VISITA', 'motivo' => 'REUNIÓN', 'activo' => true,
        ]);

        $this->carnetsResponde([]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertCount(0, $resultado['sobran']);
    }

    #[Test]
    public function si_el_carnets_no_responde_no_se_afirma_que_aqui_sobre_nadie(): void
    {
        // Lo contrario sería decir que sobra TODO el personal porque un servidor está caído.
        $this->aqui('11111111', 'ANA PÉREZ');

        Http::fake(['*' => Http::response('', 500)]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertFalse($resultado['disponible']);
        $this->assertCount(0, $resultado['sobran']);
        $this->assertCount(0, $resultado['faltan']);
    }

    #[Test]
    public function sin_token_configurado_el_cotejo_no_esta_disponible(): void
    {
        config(['carnets.token' => null]);
        $this->aqui('11111111', 'ANA PÉREZ');

        $this->assertFalse(app(CotejoConCarnets::class)->comparar()['disponible']);
    }

    #[Test]
    public function la_pantalla_de_trabajadores_lo_enseña_y_permite_cargarlos(): void
    {
        // Un comando en el servidor no lo va a usar quien lleva el personal: tiene que estar donde
        // se cargan los trabajadores.
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $this->aqui('11111111', 'ANA PÉREZ');
        $this->carnetsResponde([
            ['cedula' => '11111111', 'nombre' => 'ANA PÉREZ'],
            ['cedula' => '22222222', 'nombre' => 'LUIS GÓMEZ', 'gerencia' => 'OPERACIONES'],
        ]);

        $componente = Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->assertSee('en carnets y no aquí')
            ->assertSee('LUIS GÓMEZ')
            ->assertSee('OPERACIONES');

        // Y se puede dar de alta con lo que dice el carnets, sin teclearlo.
        $componente->call('cargarDelPadron', '22222222')->assertHasNoErrors();

        $this->assertDatabaseHas('personas', [
            'cedula' => '22222222',
            'tipo' => Persona::TRABAJADOR,
            'activo' => true,
        ]);
    }

    #[Test]
    public function el_comando_lo_dice_en_pantalla(): void
    {
        $this->aqui('11111111', 'ANA PÉREZ');

        $this->carnetsResponde([
            ['cedula' => '11111111', 'nombre' => 'ANA PÉREZ'],
            ['cedula' => '22222222', 'nombre' => 'LUIS GÓMEZ'],
        ]);

        $this->artisan('padron:cotejar')
            ->expectsOutputToContain('están activas en carnets y NO en este sistema')
            ->expectsOutputToContain('22222222')
            ->assertSuccessful();
    }

    #[Test]
    public function cargar_a_alguien_del_carnets_lo_da_de_alta_aqui(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $this->carnetsResponde([
            ['cedula' => '25303526', 'nombre' => 'YEITSON JOSE LAGUNA LEAL', 'gerencia' => 'GERENCIA DE SERVICIOS INTEGRADOS'],
        ]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->assertSee('YEITSON JOSE LAGUNA LEAL')
            ->call('cargarDelPadron', '25303526')
            ->assertSet('problema', '')
            ->assertSee('cargado desde el carnets');

        $this->assertDatabaseHas('personas', [
            'cedula' => '25303526',
            'tipo' => Persona::TRABAJADOR,
            'activo' => true,
        ]);
    }

    /**
     * Quien ya está aquí como visitante no se carga: se pasa a nómina, que es otro botón.
     *
     * Aun así la guarda se queda, porque la petición puede llegar sin pasar por la pantalla. Y
     * cuando salta, se explica: antes el rechazo se guardaba en el saco de errores y este panel no
     * pintaba ninguno, así que desde fuera era idéntico a un botón muerto.
     */
    public function test_cargar_a_quien_ya_esta_como_visitante_se_niega_y_lo_dice(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        Persona::create([
            'cedula' => '25303526',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Jheison laguna',
            'activo' => true,
        ]);

        $this->carnetsResponde([
            ['cedula' => '25303526', 'nombre' => 'YEITSON JOSE LAGUNA LEAL'],
        ]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            // Ya no se le ofrece cargar: se le ofrece pasarlo a nómina.
            ->assertSee('están como visitantes')
            ->call('cargarDelPadron', '25303526')
            ->assertSee('ya está aquí como VISITANTE');
    }

    /** Y si lo que se rompe no es el dato sino el sistema, tampoco se queda callado. */
    public function test_un_fallo_inesperado_al_cargar_tambien_se_ve(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $this->carnetsResponde([
            ['cedula' => '25303526', 'nombre' => 'YEITSON JOSE LAGUNA LEAL'],
        ]);

        $this->mock(GestionDeTrabajadores::class, function ($simulado) {
            $simulado->shouldReceive('guardar')->andThrow(new \RuntimeException('la base se cayó'));
        });

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->call('cargarDelPadron', '25303526')
            ->assertSee('No se pudo cargar');
    }

    /**
     * «No Aplica» no es una baja, y por creerlo el sistema ofrecía desactivar a diecisiete
     * personas que trabajan aquí. El carnets solo tiene tres estatus: Activo, Inactivo y No
     * Aplica; el último dice que ahí no hay nada que decir, no que la persona se fuera.
     */
    #[Test]
    public function no_aplica_no_cuenta_como_baja(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $trabaja = $this->aqui('11111111', 'SIGUE TRABAJANDO');
        $this->carnetsResponde([
            ['cedula' => '11111111', 'nombre' => 'SIGUE TRABAJANDO', 'estatus' => 'No Aplica'],
        ]);

        $cotejo = app(CotejoConCarnets::class)->comparar();

        $this->assertCount(0, $cotejo['inactivosEnCarnets'], 'No se ofrece desactivar por un «No Aplica».');
        $this->assertCount(1, $cotejo['sinConcluir'], 'Pero se enseña, para que se vea.');

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->assertSee('no se puede concluir nada')
            // Y si la petición llega igual —una pantalla vieja—, tampoco lo desactiva.
            ->call('desactivarComoEnCarnets', '11111111')
            ->assertSee('no está entre las que el carnets da de baja');

        $this->assertTrue((bool) $trabaja->fresh()->activo);
    }

    /**
     * El caso que se vio en producción: a quien pasó a Marca País le consta la baja en el carnets
     * —que es del CIIP— y sigue viniendo a trabajar todos los días.
     */
    #[Test]
    public function de_baja_en_carnets_pero_contratado_por_otro_ente_no_se_desactiva(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $ahoraEnMarcaPais = $this->aqui('22222222', 'CAMBIÓ DE ENTE', ente: 'marca-pais');
        $delCiip = $this->aqui('33333333', 'ESTE SÍ SE FUE', ente: 'ciip');

        $this->carnetsResponde([
            ['cedula' => '22222222', 'nombre' => 'CAMBIÓ DE ENTE', 'estatus' => 'Inactivo'],
            ['cedula' => '33333333', 'nombre' => 'ESTE SÍ SE FUE', 'estatus' => 'Inactivo'],
        ]);

        $cotejo = app(CotejoConCarnets::class)->comparar();

        $this->assertSame(['33333333'], $cotejo['inactivosEnCarnets']->pluck('persona.cedula')->all(),
            'Solo el del CIIP: la baja en el carnets del CIIP no dice nada de quien ya es de otro ente.');
        $this->assertSame(['22222222'], $cotejo['sinConcluir']->pluck('persona.cedula')->all());

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->call('desactivarTodosComoEnCarnets')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $ahoraEnMarcaPais->fresh()->activo, 'El de Marca País sigue pudiendo marcar.');
        $this->assertFalse((bool) $delCiip->fresh()->activo);
    }
}
