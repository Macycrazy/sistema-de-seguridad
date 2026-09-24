<?php

namespace Tests\Feature\Trabajadores;

use App\Livewire\Trabajadores\ListaDeTrabajadores;
use App\Models\EntregaDePase;
use App\Models\Pase;
use App\Models\Persona;
use App\Models\User;
use App\Services\GestionDeTrabajadores;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pasar una ficha de visitante a nómina, y la de un trabajador que se fue a visitas.
 *
 * Las dos son la misma idea: la persona es una sola y su ficha también. Lo que cambia es qué es
 * ahora. Partirla en dos dejaría su histórico repartido entre dos fichas con la misma cédula.
 */
class ConversionesDeFichaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));
        config(['carnets.token' => 'un-token', 'carnets.url' => 'https://carnet.example']);
    }

    private function visitante(string $cedula, string $nombre): Persona
    {
        return Persona::create([
            'cedula' => $cedula,
            'tipo' => Persona::INVITADO,
            'nombre' => $nombre,
            'motivo' => 'Reunión',
            'activo' => true,
        ]);
    }

    private function carnetsResponde(array $fichas): void
    {
        Http::fake(['*/api/seguridad/personal*' => Http::response([
            'total' => count($fichas),
            'personal' => array_map(fn ($f) => [
                'cedula' => $f['cedula'],
                'nombre_completo' => $f['nombre'],
                'gerencia' => $f['gerencia'] ?? 'GERENCIA A',
                'estatus' => 'Activo',
            ], $fichas),
        ])]);
    }

    #[Test]
    public function el_cotejo_separa_a_los_que_estan_aqui_como_visitantes(): void
    {
        // El caso real: vino de visita con el nombre mal tecleado y luego entró en nómina.
        $this->visitante('25303526', 'Jheison laguna');
        $this->carnetsResponde([['cedula' => '25303526', 'nombre' => 'YEITSON JOSE LAGUNA LEAL']]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->assertSee('están como visitantes')
            // Los dos nombres, que es lo que hace ver que es la misma persona.
            ->assertSee('YEITSON JOSE LAGUNA LEAL')
            ->assertSee('Jheison laguna')
            // Y NO en la lista de «no están aquí», donde cargar es imposible.
            ->assertDontSee('en carnets y no aquí');
    }

    #[Test]
    public function pasar_a_nomina_cambia_la_ficha_que_ya_habia_y_conserva_su_historico(): void
    {
        $visita = $this->visitante('25303526', 'Jheison laguna');
        $this->carnetsResponde([[
            'cedula' => '25303526',
            'nombre' => 'YEITSON JOSE LAGUNA LEAL',
            'gerencia' => 'GERENCIA DE SERVICIOS INTEGRADOS',
        ]]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('cotejarConCarnets')
            ->call('pasarANomina', '25303526')
            ->assertSet('problema', '')
            ->assertSee('pasó a nómina');

        $visita->refresh();

        $this->assertSame(Persona::TRABAJADOR, $visita->tipo);
        $this->assertSame('YEITSON JOSE LAGUNA LEAL', $visita->nombre, 'Se queda con el nombre del carnets.');
        $this->assertSame('GERENCIA DE SERVICIOS INTEGRADOS', $visita->dependencia);
        $this->assertTrue((bool) $visita->activo);
        $this->assertSame(1, Persona::where('cedula', '25303526')->count(), 'Una sola ficha, no dos.');
    }

    #[Test]
    public function no_se_pasa_a_nomina_a_quien_lleva_un_pase_sin_devolver(): void
    {
        $visita = $this->visitante('25303526', 'Jheison laguna');
        $pase = Pase::create(['codigo' => 'P-07', 'activo' => true]);
        EntregaDePase::create([
            'pase_id' => $pase->id,
            'persona_id' => $visita->id,
            'entregado_en' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(GestionDeTrabajadores::class)->convertirEnTrabajador($visita, 'YEITSON JOSE LAGUNA LEAL');
    }

    #[Test]
    public function un_trabajador_inactivo_pasa_a_visitas_y_puede_volver_a_marcar(): void
    {
        $exTrabajador = Persona::create([
            'cedula' => '11111111',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'QUIEN SE FUE',
            'dependencia' => 'GERENCIA A',
            'ente' => 'ciip',
            'activo' => false,
        ]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('pasarAVisitas', $exTrabajador->id)
            ->assertSee('pasó a visitas');

        $exTrabajador->refresh();

        $this->assertSame(Persona::INVITADO, $exTrabajador->tipo);
        $this->assertTrue((bool) $exTrabajador->activo, 'Como visita sí puede marcar: es para lo que se hace.');
        $this->assertSame('GERENCIA A', $exTrabajador->dependencia, 'Lo de nómina no se borra: deshacerlo lo devuelve entero.');
    }

    #[Test]
    public function un_trabajador_activo_no_se_pasa_a_visitas_sin_darle_de_baja_antes(): void
    {
        $activo = Persona::create([
            'cedula' => '22222222',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'SIGUE AQUÍ',
            'activo' => true,
        ]);

        Livewire::test(ListaDeTrabajadores::class)
            ->call('pasarAVisitas', $activo->id)
            ->assertSee('Primero dale de baja');

        $this->assertSame(Persona::TRABAJADOR, $activo->fresh()->tipo);
    }
}
