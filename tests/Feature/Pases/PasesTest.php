<?php

namespace Tests\Feature\Pases;

use App\Models\Pase;
use App\Models\Persona;
use App\Models\User;
use App\Services\Pases\CatalogoDePases;
use App\Services\Pases\Pases;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los pases de visitante: un objeto numerado que se presta y se devuelve.
 *
 * Las dos reglas que sostienen todo lo demás —un pase no está en dos manos, una persona no lleva
 * dos pases— son las que hacen que el contador de libres diga la verdad.
 */
class PasesTest extends TestCase
{
    use RefreshDatabase;

    private function pase(string $codigo = 'V-01'): Pase
    {
        return app(CatalogoDePases::class)->guardar($codigo);
    }

    private function visitante(string $cedula = '11111111', string $nombre = 'ANA PÉREZ'): Persona
    {
        return Persona::create([
            'cedula' => $cedula,
            'tipo' => Persona::INVITADO,
            'nombre' => $nombre,
            'motivo' => 'REUNIÓN',
            'activo' => true,
        ]);
    }

    #[Test]
    public function el_codigo_se_guarda_limpio_y_en_mayuscula(): void
    {
        $pase = $this->pase('  v-01  ');

        $this->assertSame('V-01', $pase->codigo);
    }

    #[Test]
    public function una_tanda_carga_los_pases_de_golpe_y_no_repite_los_que_ya_estan(): void
    {
        // Cargar treinta pases a mano es lo que hace que no se carguen.
        $creados = app(CatalogoDePases::class)->crearTanda('V-', 1, 20);
        $this->assertSame(20, $creados);
        $this->assertSame('V-01', Pase::orderBy('orden')->first()->codigo);

        // Ampliar la tanda no duplica nada.
        $mas = app(CatalogoDePases::class)->crearTanda('V-', 15, 25);
        $this->assertSame(5, $mas);
        $this->assertSame(25, Pase::count());
    }

    #[Test]
    public function entregar_un_pase_lo_saca_de_los_libres(): void
    {
        $pase = $this->pase();
        $ana = $this->visitante();

        $this->assertCount(1, app(Pases::class)->libres());

        app(Pases::class)->entregar($pase, $ana);

        $this->assertCount(0, app(Pases::class)->libres());
        $this->assertSame(['fuera' => 1, 'libres' => 0, 'total' => 1], app(Pases::class)->cuentas());
    }

    #[Test]
    public function un_pase_no_puede_estar_en_dos_manos(): void
    {
        $pase = $this->pase();
        app(Pases::class)->entregar($pase, $this->visitante());

        $this->expectException(ValidationException::class);
        app(Pases::class)->entregar($pase, $this->visitante('22222222', 'LUIS'));
    }

    #[Test]
    public function una_persona_no_lleva_dos_pases(): void
    {
        $ana = $this->visitante();
        app(Pases::class)->entregar($this->pase('V-01'), $ana);

        $this->expectException(ValidationException::class);
        app(Pases::class)->entregar($this->pase('V-02'), $ana);
    }

    #[Test]
    public function un_pase_deshabilitado_no_se_entrega(): void
    {
        $pase = $this->pase();
        app(CatalogoDePases::class)->habilitar($pase, false);

        $this->expectException(ValidationException::class);
        app(Pases::class)->entregar($pase->fresh(), $this->visitante());
    }

    #[Test]
    public function devolver_lo_libera_y_deja_quien_lo_recibio(): void
    {
        $guardia = User::factory()->create(['rol' => Rol::vigilante()]);
        $this->actingAs($guardia);

        $entrega = app(Pases::class)->entregar($this->pase(), $this->visitante());
        $this->assertTrue(app(Pases::class)->devolver($entrega));

        $entrega->refresh();

        $this->assertNotNull($entrega->devuelto_en);
        $this->assertSame($guardia->id, $entrega->devuelto_usuario_id);
        $this->assertCount(1, app(Pases::class)->libres());
    }

    #[Test]
    public function devolver_dos_veces_no_es_un_error(): void
    {
        // Dos guardias pueden marcar lo mismo con segundos de diferencia.
        $entrega = app(Pases::class)->entregar($this->pase(), $this->visitante());

        $this->assertTrue(app(Pases::class)->devolver($entrega));
        $this->assertFalse(app(Pases::class)->devolver($entrega->fresh()));
    }

    #[Test]
    public function un_pase_entregado_no_se_quita_del_catalogo(): void
    {
        // Borrarlo se llevaría por delante el registro de a quién se le dio.
        $pase = $this->pase();
        app(Pases::class)->entregar($pase, $this->visitante());

        $this->expectException(ValidationException::class);
        app(CatalogoDePases::class)->eliminar($pase->fresh());
    }

    #[Test]
    public function un_pase_devuelto_si_se_puede_quitar(): void
    {
        $pase = $this->pase();
        $entrega = app(Pases::class)->entregar($pase, $this->visitante());
        app(Pases::class)->devolver($entrega);

        app(CatalogoDePases::class)->eliminar($pase->fresh());

        $this->assertSame(0, Pase::count());
    }

    #[Test]
    public function se_sabe_que_pase_lleva_una_persona(): void
    {
        $ana = $this->visitante();
        app(Pases::class)->entregar($this->pase('V-07'), $ana);

        $this->assertSame('V-07', app(Pases::class)->deLaPersona($ana)?->pase?->codigo);
        $this->assertNull(app(Pases::class)->deLaPersona($this->visitante('22222222', 'LUIS')));
    }
}
