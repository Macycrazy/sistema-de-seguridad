<?php

namespace Tests\Feature\Trabajadores;

use App\Exports\PlantillaTrabajadores;
use App\Imports\TrabajadoresImport;
use App\Livewire\Trabajadores\ListaDeTrabajadores;
use App\Models\User;
use App\Services\GestionDeTrabajadores;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlantillaTrabajadoresTest extends TestCase
{
    use RefreshDatabase;

    /** Genera la plantilla y devuelve la hoja cargada, para inspeccionarla o llenarla. */
    private function hojaDeLaPlantilla(): Worksheet
    {
        $binario = Excel::raw(new PlantillaTrabajadores, ExcelFormat::XLSX);
        $ruta = tempnam(sys_get_temp_dir(), 'plantilla').'.xlsx';
        file_put_contents($ruta, $binario);

        return IOFactory::load($ruta)->getActiveSheet();
    }

    #[Test]
    public function la_plantilla_trae_los_encabezados_en_la_fila_2(): void
    {
        $hoja = $this->hojaDeLaPlantilla();

        $encabezados = array_slice($hoja->toArray(null, true, false, false)[1], 0, 6);

        $this->assertSame(['Cédula', 'Nombre', 'Apellido', 'Ente', 'Dependencia', 'Piso'], $encabezados);
    }

    #[Test]
    public function una_plantilla_llenada_se_importa_sin_adaptar_nada(): void
    {
        // La prueba que importa: se genera la plantilla, se llena una fila y se importa. Si la
        // plantilla y el importador se desalinearan, esto se caería.
        $binario = Excel::raw(new PlantillaTrabajadores, ExcelFormat::XLSX);
        $ruta = tempnam(sys_get_temp_dir(), 'plantilla').'.xlsx';
        file_put_contents($ruta, $binario);

        $spreadsheet = IOFactory::load($ruta);
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray(['12345678', 'Ana', 'Pérez', 'CIIP', 'Recursos Humanos', '3-1'], null, 'A3');
        (new Xlsx($spreadsheet))->save($ruta);

        $import = new TrabajadoresImport(app(GestionDeTrabajadores::class));
        Excel::import($import, $ruta);

        $this->assertSame(1, $import->guardados);
        $this->assertSame(0, $import->omitidos);
        $this->assertDatabaseHas('personas', [
            'cedula' => '12345678',
            'nombre' => 'ANA PÉREZ',
            'ente' => 'ciip',
            'tipo' => 'trabajador',
        ]);
    }

    #[Test]
    public function descargar_plantilla_entrega_un_archivo(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(ListaDeTrabajadores::class)
            ->call('descargarPlantilla')
            ->assertFileDownloaded('plantilla-personal.xlsx');
    }
}
