<?php

namespace Tests\Feature\Trabajadores;

use App\Models\Persona;
use App\Services\GestionDeTrabajadores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GestionDeTrabajadoresTest extends TestCase
{
    use RefreshDatabase;

    private GestionDeTrabajadores $gestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestion = app(GestionDeTrabajadores::class);
    }

    #[Test]
    public function da_de_alta_a_un_trabajador_normalizando_cedula_y_nombre(): void
    {
        $t = $this->gestion->guardar('12.345.678', 'ana pérez', 'ciip', 'recursos humanos', '3-1');

        $this->assertSame('12345678', $t->cedula);
        $this->assertSame('ANA PÉREZ', $t->nombre);
        $this->assertSame('RECURSOS HUMANOS', $t->dependencia);
        $this->assertSame(Persona::ENTE_CIIP, $t->ente);
        $this->assertSame(Persona::TRABAJADOR, $t->tipo);
        $this->assertTrue($t->activo);
    }

    #[Test]
    public function volver_a_guardar_la_misma_cedula_actualiza_en_vez_de_duplicar(): void
    {
        $this->gestion->guardar('12345678', 'ANA PÉREZ', 'ciip');
        $this->gestion->guardar('12.345.678', 'ANA MARÍA PÉREZ', 'venapp');

        $this->assertSame(1, Persona::where('cedula', '12345678')->count());
        $this->assertSame('ANA MARÍA PÉREZ', Persona::where('cedula', '12345678')->first()->nombre);
    }

    #[Test]
    public function el_ente_y_la_dependencia_son_opcionales(): void
    {
        $t = $this->gestion->guardar('12345678', 'ANA PÉREZ');

        $this->assertNull($t->ente);
        $this->assertNull($t->dependencia);
    }

    #[Test]
    public function una_cedula_fuera_de_rango_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        $this->gestion->guardar('123', 'ANA PÉREZ');
    }

    #[Test]
    public function un_ente_inventado_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        $this->gestion->guardar('12345678', 'ANA PÉREZ', 'otra-cosa');
    }

    #[Test]
    public function sin_nombre_no_se_guarda(): void
    {
        $this->expectException(ValidationException::class);
        $this->gestion->guardar('12345678', '   ');
    }

    #[Test]
    public function no_se_puede_convertir_a_un_invitado_en_trabajador(): void
    {
        Persona::create(['cedula' => '12345678', 'tipo' => Persona::INVITADO, 'nombre' => 'VISITA', 'activo' => true]);

        $this->expectException(ValidationException::class);
        $this->gestion->guardar('12345678', 'AHORA TRABAJADOR');
    }

    #[Test]
    public function al_dar_de_alta_trae_la_foto_del_carnets(): void
    {
        Storage::fake('local');
        config(['carnets.fotos' => 'http://carnets.interno/imgs/usuarios']);
        Http::fake(['*/12345678.jpg' => Http::response('LA-FOTO', 200)]);

        $t = $this->gestion->guardar('12345678', 'ANA PÉREZ');

        $this->assertSame('fotos/12345678.jpg', $t->fresh()->foto_ruta);
        $this->assertTrue($t->fresh()->tieneFoto());
    }

    #[Test]
    public function sin_foto_en_el_carnets_el_alta_no_falla(): void
    {
        Storage::fake('local');
        config(['carnets.fotos' => 'http://carnets.interno/imgs/usuarios']);
        Http::fake(['*' => Http::response('', 404)]);

        $t = $this->gestion->guardar('12345678', 'ANA PÉREZ');

        $this->assertNull($t->fresh()->foto_ruta);
        $this->assertFalse($t->fresh()->tieneFoto());
    }

    #[Test]
    public function desactivar_no_borra_y_reactivar_devuelve_el_acceso(): void
    {
        $t = $this->gestion->guardar('12345678', 'ANA PÉREZ');

        $this->gestion->desactivar($t);
        $this->assertFalse($t->fresh()->activo);
        $this->assertDatabaseHas('personas', ['cedula' => '12345678']);

        $this->gestion->reactivar($t);
        $this->assertTrue($t->fresh()->activo);
    }
}
