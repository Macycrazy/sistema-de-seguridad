<?php

namespace Tests\Feature\Carnets;

use App\Livewire\Trabajadores\ListaDeTrabajadores;
use App\Models\Persona;
use App\Models\User;
use App\Services\Carnets\CotejoConCarnets;
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
}
