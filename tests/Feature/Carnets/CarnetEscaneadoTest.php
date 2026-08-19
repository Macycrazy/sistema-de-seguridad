<?php

namespace Tests\Feature\Carnets;

use App\Livewire\Marcar;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Escanear el carnet en la puerta: verificar contra carnets y traer/actualizar la ficha.
 */
class CarnetEscaneadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['carnets.url' => 'http://carnets:8000']);
    }

    #[Test]
    public function un_carnet_activo_da_de_alta_o_actualiza_y_muestra_la_ficha(): void
    {
        Http::fake(['*/Trabajador_*' => Http::response([
            'activo' => true, 'nacionalidad' => 'V', 'cedula' => '24270727',
            'nombre' => 'JORGE DAVID CASTILLO GARCÍA', 'cargo' => 'ASESOR', 'gerencia' => 'GERENCIA DE PROTOCOLO',
        ], 200)]);

        Livewire::test(Marcar::class)
            ->call('carnetEscaneado', 'http://carnets/Trabajador_abc123')
            ->assertHasNoErrors()
            ->assertSee('JORGE DAVID CASTILLO GARCÍA');

        $this->assertDatabaseHas('personas', [
            'cedula' => '24270727',
            'tipo' => Persona::TRABAJADOR,
            'nacionalidad' => 'V',
            'dependencia' => 'GERENCIA DE PROTOCOLO',
            'activo' => true,
        ]);
    }

    #[Test]
    public function un_carnet_activo_actualiza_la_ficha_que_ya_existia(): void
    {
        Persona::create(['cedula' => '24270727', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'NOMBRE VIEJO', 'activo' => true]);

        Http::fake(['*/Trabajador_*' => Http::response([
            'activo' => true, 'nacionalidad' => 'V', 'cedula' => '24270727',
            'nombre' => 'JORGE DAVID CASTILLO GARCÍA', 'gerencia' => 'GERENCIA DE PROTOCOLO',
        ], 200)]);

        Livewire::test(Marcar::class)->call('carnetEscaneado', 'http://carnets/Trabajador_abc');

        $this->assertSame(1, Persona::where('cedula', '24270727')->count());
        $this->assertSame('JORGE DAVID CASTILLO GARCÍA', Persona::where('cedula', '24270727')->first()->nombre);
    }

    #[Test]
    public function un_carnet_no_activo_no_da_de_alta_a_nadie_y_avisa(): void
    {
        Http::fake(['*/Trabajador_*' => Http::response(['activo' => false], 200)]);

        Livewire::test(Marcar::class)
            ->call('carnetEscaneado', 'http://carnets/Trabajador_zzz')
            ->assertHasErrors('cedula');

        $this->assertSame(0, Persona::count());
    }

    #[Test]
    public function si_no_se_alcanza_al_carnets_avisa_sin_reventar(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('sin ruta')]);

        Livewire::test(Marcar::class)
            ->call('carnetEscaneado', 'http://carnets/Trabajador_abc')
            ->assertHasErrors('cedula');

        $this->assertSame(0, Persona::count());
    }

    #[Test]
    public function un_invitado_que_resulta_ser_trabajador_se_corrige_con_el_carnet(): void
    {
        // Estaba como invitado en seguridad, pero el carnet dice que es personal activo: se corrige
        // a trabajador y se le rellenan los datos. Justo para eso se escanea.
        Persona::create(['cedula' => '24270727', 'tipo' => Persona::INVITADO, 'nombre' => 'VISITA', 'activo' => true]);

        Http::fake(['*/Trabajador_*' => Http::response([
            'activo' => true, 'nacionalidad' => 'V', 'cedula' => '24270727',
            'nombre' => 'JORGE CASTILLO', 'gerencia' => 'GERENCIA DE PROTOCOLO',
        ], 200)]);

        Livewire::test(Marcar::class)
            ->call('carnetEscaneado', 'http://carnets/Trabajador_abc')
            ->assertHasNoErrors();

        $persona = Persona::where('cedula', '24270727')->first();
        $this->assertSame(Persona::TRABAJADOR, $persona->tipo);
        $this->assertSame('JORGE CASTILLO', $persona->nombre);
        $this->assertSame('GERENCIA DE PROTOCOLO', $persona->dependencia);
    }
}
