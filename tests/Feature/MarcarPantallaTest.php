<?php

namespace Tests\Feature;

use App\Livewire\Marcar;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Marcaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de la puerta, vista como la usa el vigilante: teclear una cédula y pulsar un botón.
 *
 * Lo que se comprueba aquí es el recorrido completo, incluida la promesa del módulo: marcar a
 * alguien sin escribir nada más que la cédula, y que un invitado que ya vino se marque igual.
 */
class MarcarPantallaTest extends TestCase
{
    use RefreshDatabase;

    private function trabajador(array $atributos = []): Persona
    {
        return Persona::create(array_merge([
            'cedula' => '12345678',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'Ana Rodríguez Peña',
            'dependencia' => 'Recursos Humanos',
            'activo' => true,
        ], $atributos));
    }

    public function test_la_pantalla_carga(): void
    {
        $this->get('/marcar')->assertStatus(200)->assertSeeLivewire(Marcar::class);
    }

    public function test_al_teclear_la_cedula_de_un_trabajador_salen_su_nombre_y_su_dependencia(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Ana Rodríguez Peña')
            ->assertSee('Recursos Humanos')
            ->assertSet('invitadoNuevo', false);
    }

    public function test_la_cedula_se_puede_teclear_con_puntos(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12.345.678')
            ->call('buscar')
            ->assertSee('Ana Rodríguez Peña');
    }

    public function test_una_cedula_que_no_esta_avisa_que_es_un_invitado_y_pide_dos_datos(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->assertSet('invitadoNuevo', true)
            ->assertSee('es un invitado')
            ->assertSee('Motivo de visita');
    }

    public function test_los_datos_salen_solos_sin_pulsar_enter(): void
    {
        $this->trabajador();

        // Solo se escribe la cédula. No se llama a «buscar»: nadie pulsó nada.
        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->assertSee('Ana Rodríguez Peña')
            ->assertSee('Recursos Humanos');
    }

    public function test_mientras_la_cedula_esta_a_medias_no_se_muestra_nada(): void
    {
        $this->trabajador(['cedula' => '253752'.'58']);

        // Al teclear «25375258» se pasa por estos pasos. Ninguno debe mostrar nada:
        // ni una ficha equivocada, ni el aviso de invitado, ni un error.
        $componente = Livewire::test(Marcar::class);

        foreach (['2', '25', '253', '2537', '25375'] as $aMedias) {
            $componente->set('cedula', $aMedias)
                ->assertSet('invitadoNuevo', false)
                ->assertSet('personaId', null)
                ->assertHasNoErrors()
                ->assertDontSee('es un invitado');
        }
    }

    public function test_al_completar_la_cedula_aparece_la_persona(): void
    {
        $this->trabajador(['cedula' => '25375258', 'nombre' => 'María Fernández']);

        Livewire::test(Marcar::class)
            ->set('cedula', '2537525')      // a medias: nada
            ->assertSet('personaId', null)
            ->set('cedula', '25375258')     // completa: sale
            ->assertSee('María Fernández');
    }

    public function test_una_cedula_desconocida_ya_completa_si_avisa_que_es_un_invitado(): void
    {
        // Sin pulsar Enter: con seis dígitos ya se puede afirmar que no está.
        Livewire::test(Marcar::class)
            ->set('cedula', '876543')
            ->assertSet('invitadoNuevo', true)
            ->assertSee('es un invitado');
    }

    public function test_borrar_la_cedula_deja_la_pantalla_como_al_principio(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->assertSee('Ana Rodríguez Peña')
            ->set('cedula', '')
            ->assertSet('personaId', null)
            ->assertSet('invitadoNuevo', false)
            ->assertHasNoErrors()
            ->assertDontSee('Ana Rodríguez Peña');
    }

    public function test_teclear_no_borra_lo_que_ya_se_escribio_de_la_ficha_del_invitado(): void
    {
        $componente = Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia');

        // Otra búsqueda de la misma cédula desconocida no debe vaciar lo ya escrito.
        $componente->set('cedula', '87654321')
            ->assertSet('nombre', 'Carlos Pérez')
            ->assertset('motivo', 'Videoconferencia');
    }

    public function test_pulsar_enter_sigue_funcionando_para_el_lector_de_carnets(): void
    {
        $this->trabajador();

        // El lector teclea la cédula y termina con un Enter, que es lo que hace «buscar».
        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('Ana Rodríguez Peña');
    }

    public function test_el_campo_no_deja_teclear_letras_ni_pasar_del_maximo(): void
    {
        // Se comprueba en el HTML porque esto lo impone el navegador. La regla de verdad la pone
        // el servidor, y eso lo cubre MarcajeTest.
        $html = $this->get('/marcar')->assertOk()->getContent();

        $this->assertStringContainsString('maxlength="'.Marcaje::DIGITOS_MAXIMOS.'"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('replace(/[^0-9]/g', $html);
    }

    public function test_una_cedula_con_mas_digitos_de_los_posibles_no_busca_a_nadie(): void
    {
        $this->trabajador(['cedula' => '12345678']);

        // Aunque el campo lo impida, el componente no se fía: 14 dígitos no son una cédula.
        Livewire::test(Marcar::class)
            ->set('cedula', '12345678901234')
            ->assertSet('personaId', null)
            ->assertSet('invitadoNuevo', false)
            ->assertDontSee('Ana Rodríguez Peña');
    }

    public function test_si_llegan_letras_mezcladas_solo_cuentan_los_digitos(): void
    {
        $this->trabajador(['cedula' => '12345678']);

        // Nadie puede teclear esto en la pantalla, pero si llega, se entiende como la cédula.
        Livewire::test(Marcar::class)
            ->set('cedula', '1a2b3c4d5e6f7g8')
            ->assertSee('Ana Rodríguez Peña');
    }

    public function test_una_cedula_invalida_no_llega_a_buscar_nada(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '123')
            ->call('buscar')
            ->assertHasErrors('cedula')
            ->assertSet('invitadoNuevo', false)
            ->assertSet('personaId', null);
    }

    public function test_marcar_la_entrada_deja_el_movimiento_y_limpia_la_pantalla(): void
    {
        $persona = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada')
            // La pantalla queda lista para el siguiente, sin tocar nada.
            ->assertSet('cedula', '')
            ->assertSet('personaId', null)
            ->assertSee('Entrada registrada')
            ->assertSee('Ana Rodríguez Peña');

        $this->assertDatabaseHas('movimientos', [
            'persona_id' => $persona->id,
            'tipo' => Movimiento::ENTRADA,
        ]);
    }

    public function test_a_quien_ya_esta_dentro_la_pantalla_le_propone_la_salida(): void
    {
        $persona = $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('marcarEntrada');

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('lo que toca es la salida');
    }

    public function test_el_alta_de_un_invitado_lo_deja_listo_para_marcar_sin_teclear_la_cedula_otra_vez(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            ->call('guardarInvitado')
            ->assertSet('invitadoNuevo', false)
            ->assertSee('Carlos Pérez')
            // Ya se puede pulsar el botón: la cédula sigue siendo la misma.
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseHas('personas', [
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
        ]);
    }

    public function test_el_alta_de_un_invitado_guarda_el_vehiculo_en_el_que_llego(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            ->set('marca', 'Toyota')
            ->set('modelo', 'Corolla')
            ->set('color', 'Gris')
            ->set('placa', 'ab-123-cd')
            ->call('guardarInvitado')
            ->assertSet('invitadoNuevo', false)
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        // La placa se guarda normalizada aunque se teclee con guiones y en minúsculas.
        $this->assertDatabaseHas('personas', [
            'cedula' => '87654321',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'color' => 'Gris',
            'placa' => 'AB123CD',
        ]);

        // Y el asiento se lleva su copia del día.
        $this->assertDatabaseHas('movimientos', ['placa' => 'AB123CD']);
    }

    public function test_un_invitado_a_pie_se_guarda_sin_vehiculo(): void
    {
        // El vehículo no es obligatorio: la mayoría de la gente entra caminando.
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            ->call('guardarInvitado')
            ->assertHasNoErrors()
            ->assertSet('invitadoNuevo', false);

        $this->assertDatabaseHas('personas', ['cedula' => '87654321', 'placa' => null]);
    }

    public function test_un_vehiculo_a_medias_avisa_de_que_falta_la_placa(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', 'Videoconferencia')
            ->set('marca', 'Toyota')
            ->set('color', 'Gris')
            ->call('guardarInvitado')
            ->assertHasErrors('placa')
            ->assertSet('invitadoNuevo', true);

        $this->assertDatabaseMissing('personas', ['cedula' => '87654321']);
    }

    public function test_al_invitado_que_vuelve_le_sale_escrito_el_carro_de_la_ultima_vez(): void
    {
        Persona::create([
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'color' => 'Gris',
            'placa' => 'AB123CD',
            'activo' => true,
        ]);

        // No hay que volver a preguntárselo: sale escrito y solo se confirma.
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->assertSet('marca', 'Toyota')
            ->assertSet('modelo', 'Corolla')
            ->assertSet('color', 'Gris')
            ->assertSet('placa', 'AB123CD');
    }

    public function test_un_invitado_sin_los_dos_datos_no_se_guarda(): void
    {
        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            ->set('nombre', 'Carlos Pérez')
            ->set('motivo', '')
            ->call('guardarInvitado')
            ->assertHasErrors('motivo')
            ->assertSet('invitadoNuevo', true);

        $this->assertDatabaseMissing('personas', ['cedula' => '87654321']);
    }

    public function test_un_invitado_que_ya_vino_se_marca_solo_con_la_cedula(): void
    {
        // Vino ayer y quedó registrado.
        Persona::create([
            'cedula' => '87654321',
            'tipo' => Persona::INVITADO,
            'nombre' => 'Carlos Pérez',
            'motivo' => 'Videoconferencia',
            'activo' => true,
        ]);

        Livewire::test(Marcar::class)
            ->set('cedula', '87654321')
            ->call('buscar')
            // No vuelve a pedir el formulario: ya sabe quién es.
            ->assertSet('invitadoNuevo', false)
            ->assertSee('Carlos Pérez')
            ->assertset('motivo', 'Videoconferencia')
            ->call('marcarEntrada')
            ->assertSee('Entrada registrada');

        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_a_una_persona_desactivada_la_pantalla_no_la_deja_marcar(): void
    {
        $persona = $this->trabajador(['activo' => false]);

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->assertSee('está desactivada')
            ->call('marcarEntrada')
            ->assertHasErrors('cedula');

        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_el_contador_de_quienes_estan_dentro_se_ve_en_la_pantalla(): void
    {
        $ana = $this->trabajador(['cedula' => '11111111', 'nombre' => 'Ana']);
        $this->trabajador(['cedula' => '22222222', 'nombre' => 'Luis']);

        Livewire::test(Marcar::class)->assertSee('Dentro ahora');

        Livewire::test(Marcar::class)
            ->set('cedula', '11111111')
            ->call('buscar')
            ->call('marcarEntrada');

        // Con una persona dentro, el contador lo dice.
        $this->assertSame(1, app(Marcaje::class)->cuantosDentro());
    }

    public function test_cancelar_devuelve_la_pantalla_a_su_estado_inicial(): void
    {
        $this->trabajador();

        Livewire::test(Marcar::class)
            ->set('cedula', '12345678')
            ->call('buscar')
            ->call('limpiar')
            ->assertSet('cedula', '')
            ->assertSet('personaId', null)
            ->assertSet('invitadoNuevo', false)
            ->assertDontSee('Ana Rodríguez Peña');
    }
}
