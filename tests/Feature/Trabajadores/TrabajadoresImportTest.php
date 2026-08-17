<?php

namespace Tests\Feature\Trabajadores;

use App\Imports\TrabajadoresImport;
use App\Models\Persona;
use App\Services\GestionDeTrabajadores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El importador implementa ToCollection, así que se prueba llamándole collection() con filas
 * hechas a mano: sin tocar archivos, pero pasando por la misma lógica que un Excel de verdad.
 */
class TrabajadoresImportTest extends TestCase
{
    use RefreshDatabase;

    private function importar(array $filas): TrabajadoresImport
    {
        $import = new TrabajadoresImport(app(GestionDeTrabajadores::class));
        $import->collection(new Collection(array_map(fn ($f) => new Collection($f), $filas)));

        return $import;
    }

    #[Test]
    public function carga_las_filas_saltando_el_titulo_y_encontrando_los_encabezados(): void
    {
        $import = $this->importar([
            ['LISTADO DE PERSONAL', '', '', ''],                 // fila de título, se ignora
            ['Nacionalidad', 'Nombre', 'Apellido', 'Cédula', 'Departamento'], // encabezados
            ['V', 'Ana', 'Pérez', '12.345.678', 'Recursos Humanos'],
            ['V', 'Luis', 'Mora', '23456789', 'Tecnología'],
        ]);

        $this->assertSame(2, $import->guardados);
        $this->assertSame(0, $import->omitidos);
        $this->assertDatabaseHas('personas', ['cedula' => '12345678', 'nombre' => 'ANA PÉREZ', 'tipo' => 'trabajador']);
        $this->assertDatabaseHas('personas', ['cedula' => '23456789', 'nombre' => 'LUIS MORA']);
    }

    #[Test]
    public function una_fila_con_error_se_reporta_por_su_numero_sin_tumbar_a_las_buenas(): void
    {
        $import = $this->importar([
            ['Cédula', 'Nombre'],
            ['12345678', 'Ana Pérez'],   // buena
            ['123', 'Cédula Corta'],     // cédula fuera de rango
            ['34567890', 'Luis Mora'],   // buena
        ]);

        $this->assertSame(2, $import->guardados);
        $this->assertSame(1, $import->omitidos);
        // El error va con el número de fila del Excel (la corta es la fila 3).
        $this->assertArrayHasKey(3, $import->errores);
        $this->assertDatabaseHas('personas', ['cedula' => '12345678']);
        $this->assertDatabaseHas('personas', ['cedula' => '34567890']);
        $this->assertDatabaseMissing('personas', ['nombre' => 'CÉDULA CORTA']);
    }

    #[Test]
    public function traduce_el_ente_del_excel_a_la_clave_interna(): void
    {
        $import = $this->importar([
            ['Cédula', 'Nombre', 'Ente'],
            ['12345678', 'Ana Pérez', 'Marca País'],
            ['23456789', 'Luis Mora', 'VENAPP'],
        ]);

        $this->assertSame(2, $import->guardados);
        $this->assertSame(Persona::ENTE_MARCA_PAIS, Persona::where('cedula', '12345678')->first()->ente);
        $this->assertSame(Persona::ENTE_VENAPP, Persona::where('cedula', '23456789')->first()->ente);
    }

    #[Test]
    public function las_filas_vacias_se_saltan(): void
    {
        $import = $this->importar([
            ['Cédula', 'Nombre'],
            ['12345678', 'Ana Pérez'],
            ['', ''],
            ['', ''],
        ]);

        $this->assertSame(1, $import->guardados);
        $this->assertSame(0, $import->omitidos);
    }
}
