<?php

namespace Tests\Feature\Auditoria;

use App\Auditoria\Accion;
use App\Livewire\Auditoria\ElRastro;
use App\Models\Persona;
use App\Models\User;
use App\Services\Marcaje;
use App\Services\Rastro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La pantalla del rastro.
 *
 * La prueba que importa es la última: la pregunta del README —«quién consultó los datos de una
 * persona y en qué momento»— respondida en la tabla, que es donde se lee desde que la pantalla no
 * tiene recuadro para buscar dentro del detalle.
 */
class PantallaDeAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        $jefa = User::factory()->administrador()->create(['nombre' => 'Carmen Díaz Silva']);

        $this->actingAs($jefa);

        return $jefa;
    }

    #[Test]
    public function solo_la_abre_quien_tiene_el_permiso(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $this->get('/auditoria')->assertForbidden();
        Livewire::test(ElRastro::class)->assertForbidden();

        $this->administrador();
        $this->get('/auditoria')->assertOk();
    }

    #[Test]
    public function sin_nada_anotado_lo_dice_en_vez_de_una_tabla_vacia(): void
    {
        $this->administrador();

        Livewire::test(ElRastro::class)->assertSee('Todavía no hay nada anotado');
    }

    #[Test]
    public function filtra_por_accion(): void
    {
        $jefa = $this->administrador();
        $rastro = app(Rastro::class);

        $rastro->deja(Accion::REGISTRO_EXPORTADO, detalle: 'el dia entero');
        $rastro->deja(Accion::CONSULTA_CEDULA, detalle: '12345678');

        Livewire::test(ElRastro::class)
            ->set('accion', Accion::REGISTRO_EXPORTADO->value)
            ->assertSee('el dia entero')
            ->assertDontSee('12345678');

        $this->assertNotNull($jefa->id);
    }

    #[Test]
    public function filtra_por_usuario(): void
    {
        $jefa = $this->administrador();
        $otro = User::factory()->create(['nombre' => 'Luis Hernández Mora']);

        app(Rastro::class)->deja(Accion::CONSULTA_CEDULA, detalle: '11111111');
        app(Rastro::class)->deja(Accion::CONSULTA_CEDULA, detalle: '22222222', usuarioId: $otro->id);

        Livewire::test(ElRastro::class)
            ->set('usuario', (string) $jefa->id)
            ->assertSee('11111111')
            ->assertDontSee('22222222');
    }

    #[Test]
    public function filtra_por_fecha(): void
    {
        $this->administrador();

        $this->travelTo(now()->subDays(3));
        app(Rastro::class)->deja(Accion::CONSULTA_CEDULA, detalle: '11111111');

        $this->travelBack();
        app(Rastro::class)->deja(Accion::CONSULTA_CEDULA, detalle: '22222222');

        Livewire::test(ElRastro::class)
            ->set('desde', now()->subDay()->toDateString())
            ->assertSee('22222222')
            ->assertDontSee('11111111');
    }

    #[Test]
    public function una_fecha_ilegible_en_la_url_no_rompe_la_pantalla(): void
    {
        $this->administrador();
        app(Rastro::class)->deja(Accion::CONSULTA_CEDULA, detalle: '12345678');

        Livewire::test(ElRastro::class)
            ->set('desde', 'lo-que-sea')
            ->assertOk()
            ->assertSee('12345678');
    }

    #[Test]
    public function quitar_los_filtros_los_quita_todos(): void
    {
        $this->administrador();
        app(Rastro::class)->deja(Accion::CONSULTA_CEDULA, detalle: '12345678');

        Livewire::test(ElRastro::class)
            ->set('accion', Accion::REGISTRO_EXPORTADO->value)
            ->set('hasta', '2000-01-01')
            ->assertDontSee('12345678')
            ->call('limpiar')
            ->assertSet('accion', '')
            ->assertSet('hasta', '')
            ->assertSee('12345678');
    }

    /**
     * La pregunta del README, entera: se teclea la cédula de alguien y sale quién la consultó, con
     * su nombre y la hora. Esto es lo que decide si la parte 3 está terminada.
     */
    #[Test]
    public function responde_quien_consulto_los_datos_de_una_persona(): void
    {
        $persona = Persona::create([
            'cedula' => '12345678',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'Ana Rodríguez Peña',
            'dependencia' => 'Recursos Humanos',
            'activo' => true,
        ]);

        // El vigilante la consulta en la puerta.
        $vigilante = User::factory()->create(['nombre' => 'Luis Hernández Mora']);
        $this->actingAs($vigilante);
        app(Marcaje::class)->buscarPorCedula('12345678');

        // Y alguien más consulta a otra persona, para que haya con qué confundirse.
        $this->actingAs(User::factory()->create(['nombre' => 'Rosa Blanco Ceballos']));
        app(Marcaje::class)->buscarPorCedula('87654321');

        // La administradora pregunta por esa cédula.
        $this->administrador();

        /*
         * Se comprueba sobre las FILAS de la tabla y no sobre la página entera. Los filtros de
         * arriba llevan un desplegable con todas las acciones y otro con todos los usuarios, así
         * que «Consultó una cédula» y «Rosa Blanco Ceballos» salen en la página pase lo que pase:
         * buscarlos ahí no probaría nada.
         */
        $filas = $this->filas(Livewire::test(ElRastro::class)->html());

        // Sin recuadro que busque en el detalle, la cédula se busca donde el administrador la
        // busca ahora: leyendo la columna «Sobre qué» de la tabla.
        $suyas = array_values(array_filter($filas, fn (string $fila) => str_contains($fila, '12345678')));

        $this->assertCount(1, $suyas);
        $this->assertStringContainsString('Luis Hernández Mora', $suyas[0]);
        $this->assertStringContainsString('Consultó una cédula', $suyas[0]);
        $this->assertStringContainsString($persona->nombre, $suyas[0]);
    }

    /**
     * Las filas de la tabla, en texto plano.
     *
     * @return array<int, string>
     */
    private function filas(string $html): array
    {
        preg_match_all('~<tr wire:key="asiento-\d+">(.*?)</tr>~s', $html, $coincidencias);

        return array_map(
            fn (string $fila) => preg_replace('/\s+/', ' ', trim(strip_tags($fila))),
            $coincidencias[1],
        );
    }
}
