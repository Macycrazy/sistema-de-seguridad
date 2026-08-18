<?php

namespace Tests\Feature\Edificio;

use App\Models\Oficina;
use App\Services\Edificio\CatalogoDelEdificio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogoDelEdificioTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoDelEdificio $catalogo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogo = app(CatalogoDelEdificio::class);
    }

    #[Test]
    public function la_migracion_sembro_el_catalogo_desde_la_config(): void
    {
        // La tabla arranca con lo que estaba en config/edificio.php.
        $this->assertSame(count(config('edificio.oficinas')), Oficina::count());
        $this->assertContains('LOBBY', $this->catalogo->oficinas());
        $this->assertSame('Presidencia', $this->catalogo->nombres()['9'] ?? null);
    }

    #[Test]
    public function las_oficinas_salen_en_orden(): void
    {
        Oficina::query()->delete();
        Oficina::create(['codigo' => 'B', 'orden' => 2]);
        Oficina::create(['codigo' => 'A', 'orden' => 1]);

        $this->assertSame(['A', 'B'], $this->catalogo->oficinas());
    }

    #[Test]
    public function si_la_tabla_esta_vacia_cae_a_la_config(): void
    {
        Oficina::query()->delete();

        $this->assertSame(array_values(config('edificio.oficinas')), $this->catalogo->oficinas());
        $this->assertSame(config('edificio.nombres'), $this->catalogo->nombres());
    }

    #[Test]
    public function guardar_da_de_alta_en_mayusculas_y_actualiza_por_codigo(): void
    {
        $this->catalogo->guardar('5-3', 'algo');
        $this->catalogo->guardar('5-3', 'Otra cosa');

        $this->assertSame(1, Oficina::where('codigo', '5-3')->count());
        $this->assertSame('Otra cosa', Oficina::where('codigo', '5-3')->first()->nombre);
    }

    #[Test]
    public function una_oficina_sin_codigo_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        $this->catalogo->guardar('   ');
    }

    #[Test]
    public function eliminar_la_quita_del_catalogo(): void
    {
        $oficina = $this->catalogo->guardar('9-9');

        $this->catalogo->eliminar($oficina);

        $this->assertDatabaseMissing('oficinas', ['codigo' => '9-9']);
    }
}
