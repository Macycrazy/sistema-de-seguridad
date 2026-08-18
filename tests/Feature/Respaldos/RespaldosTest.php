<?php

namespace Tests\Feature\Respaldos;

use App\Services\Auditoria\Auditoria;
use App\Services\Respaldos\Respaldos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class RespaldosTest extends TestCase
{
    use RefreshDatabase;

    /** Un Respaldos que no llama a pg_dump: escribe un volcado de mentira, para probar el resto. */
    private function servicioFalso(): Respaldos
    {
        $falso = new class extends Respaldos
        {
            protected function volcar(string $destino): void
            {
                file_put_contents($destino, "-- respaldo de prueba\n");
            }
        };

        $this->app->instance(Respaldos::class, $falso);

        return $falso;
    }

    #[Test]
    public function crear_deja_un_archivo_que_luego_se_lista(): void
    {
        Storage::fake('local');
        $servicio = $this->servicioFalso();

        $resultado = $servicio->crear();

        $this->assertStringEndsWith('.sql', $resultado['archivo']);
        $this->assertGreaterThan(0, $resultado['bytes']);

        $lista = $servicio->listar();
        $this->assertCount(1, $lista);
        $this->assertSame($resultado['archivo'], $lista->first()['nombre']);
    }

    #[Test]
    public function eliminar_lo_quita(): void
    {
        Storage::fake('local');
        $servicio = $this->servicioFalso();
        $resultado = $servicio->crear();

        $servicio->eliminar($resultado['archivo']);

        $this->assertCount(0, $servicio->listar());
    }

    #[Test]
    public function un_nombre_que_no_es_sql_se_rechaza(): void
    {
        Storage::fake('local');
        $this->expectException(RuntimeException::class);

        app(Respaldos::class)->eliminar('../../.env');
    }

    #[Test]
    public function el_comando_crea_y_deja_rastro(): void
    {
        Storage::fake('local');
        $this->servicioFalso();

        $this->artisan('respaldo:crear')->assertSuccessful();

        $this->assertSame(1, app(Respaldos::class)->listar()->count());
        $this->assertDatabaseHas('bitacora', ['accion' => Auditoria::RESPALDO]);
    }
}
