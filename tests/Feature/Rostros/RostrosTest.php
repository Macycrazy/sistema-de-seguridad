<?php

namespace Tests\Feature\Rostros;

use App\Livewire\Marcar;
use App\Livewire\Rostros\Indice;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Rostro;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Services\Rostros\Rostros;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El índice de rostros y lo que la puerta hace con él.
 *
 * El reconocimiento en sí ocurre en el navegador y no se puede probar desde aquí; lo que sí se
 * prueba —y es lo que importa— es que el servidor guarda solo lo que debe, que la galería no
 * lleva de más, y sobre todo que reconocer una cara NO marca a nadie.
 */
class RostrosTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, float> Un descriptor cualquiera, con la forma correcta. */
    private function descriptor(float $semilla = 0.1): array
    {
        return array_fill(0, Rostro::LARGO, $semilla);
    }

    private function trabajador(string $cedula = '12345678', string $nombre = 'ANA RODRÍGUEZ'): Persona
    {
        return Persona::create([
            'cedula' => $cedula,
            'tipo' => Persona::TRABAJADOR,
            'nombre' => $nombre,
            'dependencia' => 'RECURSOS HUMANOS',
            'activo' => true,
        ]);
    }

    #[Test]
    public function guarda_el_descriptor_que_calculo_el_navegador(): void
    {
        $ana = $this->trabajador();

        app(Rostros::class)->guardar($ana, $this->descriptor());

        $this->assertDatabaseHas('rostros', ['persona_id' => $ana->id, 'origen' => 'carnet']);
        $this->assertCount(Rostro::LARGO, Rostro::first()->descriptor);
    }

    #[Test]
    public function un_descriptor_con_forma_rara_no_se_guarda(): void
    {
        // Llega por la red desde el navegador: puede venir cualquier cosa.
        $ana = $this->trabajador();

        $this->expectException(ValidationException::class);
        app(Rostros::class)->guardar($ana, [1.0, 2.0, 3.0]);
    }

    #[Test]
    public function reindexar_pisa_el_rostro_anterior_y_no_crea_otro(): void
    {
        $ana = $this->trabajador();

        app(Rostros::class)->guardar($ana, $this->descriptor(0.1));
        app(Rostros::class)->guardar($ana, $this->descriptor(0.9));

        $this->assertSame(1, Rostro::count());
        $this->assertSame(0.9, Rostro::first()->descriptor[0]);
    }

    #[Test]
    public function la_galeria_lleva_lo_justo_y_ninguna_foto(): void
    {
        $ana = $this->trabajador();
        app(Rostros::class)->guardar($ana, $this->descriptor());

        $galeria = app(Rostros::class)->galeria();

        $this->assertCount(1, $galeria);
        $this->assertSame(['cedula', 'nombre', 'descriptor'], array_keys($galeria[0]));
        $this->assertSame($ana->cedula, $galeria[0]['cedula']);
    }

    #[Test]
    public function quien_se_desactiva_desaparece_de_la_galeria(): void
    {
        $ana = $this->trabajador();
        app(Rostros::class)->guardar($ana, $this->descriptor());

        $ana->update(['activo' => false]);

        $this->assertCount(0, app(Rostros::class)->galeria());
    }

    #[Test]
    public function a_los_visitantes_no_se_les_indexa(): void
    {
        // Su foto no está en ninguna parte, y guardarles la cara sería recoger biometría de quien
        // solo viene de visita.
        Persona::create([
            'cedula' => '99999999', 'tipo' => Persona::INVITADO,
            'nombre' => 'VISITA', 'motivo' => 'X', 'activo' => true,
        ]);

        $this->assertCount(0, app(Rostros::class)->indexables());
    }

    #[Test]
    public function el_rostro_se_va_con_la_persona(): void
    {
        // Es un dato personal: si la persona se borra, su cara no puede quedarse.
        $ana = $this->trabajador();
        app(Rostros::class)->guardar($ana, $this->descriptor());

        $ana->delete();

        $this->assertSame(0, Rostro::count());
    }

    #[Test]
    public function el_indice_se_puede_vaciar_entero(): void
    {
        // Es la salida si esto se decide no usar.
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        app(Rostros::class)->guardar($this->trabajador(), $this->descriptor());
        app(Rostros::class)->guardar($this->trabajador('87654321', 'LUIS'), $this->descriptor());

        Livewire::test(Indice::class)->call('vaciar')->assertHasNoErrors();

        $this->assertSame(0, Rostro::count());
        $this->assertDatabaseHas('bitacora', ['accion' => Auditoria::BORRO_ROSTROS]);
    }

    #[Test]
    public function reconocer_una_cara_deja_la_ficha_pero_n_o_marca_a_nadie(): void
    {
        // Lo más importante de todo el módulo: propone, no decide. Un parecido no es una
        // identificación, y quien marca es el vigilante mirando la foto.
        $this->entrandoComo();

        $ana = $this->trabajador();
        app(Rostros::class)->guardar($ana, $this->descriptor());

        Livewire::test(Marcar::class)
            ->call('rostroReconocido', $ana->cedula, 0.31)
            ->assertHasNoErrors()
            ->assertSet('cedula', $ana->cedula)
            ->assertSee('ANA RODRÍGUEZ');

        $this->assertSame(0, Movimiento::count(), 'Reconocer no puede marcar.');
        $this->assertDatabaseHas('bitacora', [
            'accion' => Auditoria::IDENTIFICO_POR_ROSTRO,
            'sobre' => $ana->cedula,
        ]);
    }

    #[Test]
    public function una_cedula_reconocida_que_ya_no_existe_lo_dice(): void
    {
        $this->entrandoComo();

        Livewire::test(Marcar::class)
            ->call('rostroReconocido', '55555555', 0.2)
            ->assertHasErrors('cedula');
    }

    #[Test]
    public function sin_rostros_indexados_la_puerta_no_ofrece_buscar_por_la_cara(): void
    {
        // Sin índice no hay con qué comparar, y un botón que no puede funcionar estorba.
        $this->entrandoComo();

        Livewire::test(Marcar::class)->assertDontSee('Buscar por la cara');
    }

    #[Test]
    public function con_rostros_indexados_si_lo_ofrece(): void
    {
        $this->entrandoComo();
        app(Rostros::class)->guardar($this->trabajador(), $this->descriptor());

        Livewire::test(Marcar::class)->assertSee('Buscar por la cara');
    }
}
