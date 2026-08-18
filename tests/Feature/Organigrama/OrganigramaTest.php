<?php

namespace Tests\Feature\Organigrama;

use App\Models\Departamento;
use App\Models\Persona;
use App\Services\Organigrama\Organigrama;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganigramaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function crear_infiere_el_nivel_del_nombre(): void
    {
        $org = app(Organigrama::class);

        $this->assertSame(0, $org->crear('PRESIDENCIA')->nivel);
        $this->assertSame(1, $org->crear('GERENCIA GENERAL DE GESTIÓN ADMINISTRATIVA')->nivel);
        $this->assertSame(2, $org->crear('GERENCIA DE LITIGIOS')->nivel);
        $this->assertSame(3, $org->crear('COORDINACIÓN DE ESTUDIOS')->nivel);
    }

    #[Test]
    public function una_hija_toma_el_nivel_de_su_madre_mas_uno(): void
    {
        $org = app(Organigrama::class);
        $madre = $org->crear('GERENCIA DE LITIGIOS');   // nivel 2

        $hija = $org->crear('Sala de audiencias', null, $madre->id);

        $this->assertSame(3, $hija->nivel);
        $this->assertSame($madre->id, $hija->parent_id);
    }

    #[Test]
    public function un_nombre_vacio_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        app(Organigrama::class)->crear('   ');
    }

    #[Test]
    public function no_se_puede_colgar_una_unidad_de_su_propia_rama(): void
    {
        $org = app(Organigrama::class);
        $abuela = $org->crear('GERENCIA GENERAL X');
        $madre = $org->crear('GERENCIA Y', null, $abuela->id);
        $hija = $org->crear('COORDINACIÓN Z', null, $madre->id);

        $this->expectException(ValidationException::class);
        // Colgar la abuela de su propia nieta haría un bucle.
        $org->mover($abuela, $hija->id);
    }

    #[Test]
    public function mover_a_la_raiz_deja_la_unidad_sin_madre(): void
    {
        $org = app(Organigrama::class);
        $madre = $org->crear('GERENCIA A');
        $hija = $org->crear('COORDINACIÓN B', null, $madre->id);

        $org->mover($hija, null);

        $this->assertNull($hija->fresh()->parent_id);
    }

    #[Test]
    public function una_unidad_con_hijas_no_se_borra(): void
    {
        $org = app(Organigrama::class);
        $madre = $org->crear('GERENCIA A');
        $org->crear('COORDINACIÓN B', null, $madre->id);

        $this->expectException(ValidationException::class);
        $org->eliminar($madre);
    }

    #[Test]
    public function borrar_una_unidad_desenlaza_a_su_gente_sin_perderla(): void
    {
        $org = app(Organigrama::class);
        $dep = $org->crear('GERENCIA A');
        $persona = Persona::create(['cedula' => '1', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA', 'activo' => true, 'departamento_id' => $dep->id]);

        $org->eliminar($dep);

        $this->assertNull($persona->fresh()->departamento_id);
        $this->assertTrue($persona->fresh()->exists);
    }

    #[Test]
    public function para_texto_encuentra_sin_distinguir_mayusculas_y_no_duplica(): void
    {
        $org = app(Organigrama::class);
        $primero = $org->crear('Gestión Humana');

        $mismo = $org->paraTexto('GESTIÓN HUMANA');

        $this->assertSame($primero->id, $mismo->id);
        $this->assertSame(1, Departamento::count());
    }

    #[Test]
    public function en_orden_arma_el_arbol_con_profundidad(): void
    {
        $org = app(Organigrama::class);
        $madre = $org->crear('GERENCIA A');
        $org->crear('COORDINACIÓN B', null, $madre->id);

        $orden = $org->enOrden();

        $this->assertSame('GERENCIA A', $orden[0]->nombre);
        $this->assertSame(0, $orden[0]->_profundidad);
        $this->assertSame('COORDINACIÓN B', $orden[1]->nombre);
        $this->assertSame(1, $orden[1]->_profundidad);
    }
}
