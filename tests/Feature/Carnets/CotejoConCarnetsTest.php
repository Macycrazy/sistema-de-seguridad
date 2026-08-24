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

    /** @param  array<int, array{cedula:string, nombre:string}>  $fichas */
    private function carnetsResponde(array $fichas): void
    {
        Http::fake([
            '*/api/seguridad/personal*' => Http::response([
                'total' => count($fichas),
                'personal' => array_map(fn ($f) => [
                    'cedula' => $f['cedula'],
                    'nombre_completo' => $f['nombre'],
                    'gerencia' => $f['gerencia'] ?? 'GERENCIA A',
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
    public function saca_a_quien_sigue_activo_aqui_y_ya_no_en_carnets(): void
    {
        $this->aqui('11111111', 'ANA PÉREZ');
        $this->aqui('33333333', 'QUIEN SE FUE');   // del CIIP: de ese sí se puede decir algo

        $this->carnetsResponde([['cedula' => '11111111', 'nombre' => 'ANA PÉREZ']]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertCount(1, $resultado['sobran']);
        $this->assertSame('33333333', $resultado['sobran'][0]->cedula);
    }

    #[Test]
    public function quien_esta_desactivado_aqui_cuenta_como_que_falta(): void
    {
        // Está activo en carnets pero aquí desactivado: en la puerta no se le puede marcar, que es
        // el problema que se busca.
        $this->aqui('11111111', 'ANA PÉREZ', activo: false);

        $this->carnetsResponde([['cedula' => '11111111', 'nombre' => 'ANA PÉREZ']]);

        $resultado = app(CotejoConCarnets::class)->comparar();

        $this->assertCount(1, $resultado['faltan']);
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
