<?php

namespace Tests\Feature\Estacionamiento;

use App\Models\Puesto;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\CatalogoDePuestos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogoDePuestosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guarda_un_puesto_con_el_codigo_en_mayusculas(): void
    {
        $puesto = app(CatalogoDePuestos::class)->guardar('a-1', DatosVehiculo::CARRO, 'Sótano 1');

        $this->assertDatabaseHas('puestos', ['codigo' => 'A-1', 'tipo' => 'carro', 'zona' => 'Sótano 1', 'activo' => true]);
        $this->assertTrue($puesto->activo);
    }

    #[Test]
    public function el_mismo_codigo_no_se_duplica(): void
    {
        $catalogo = app(CatalogoDePuestos::class);
        $catalogo->guardar('A-1', DatosVehiculo::CARRO);
        $catalogo->guardar('A-1', DatosVehiculo::MOTO, 'Frente');

        $this->assertSame(1, Puesto::where('codigo', 'A-1')->count());
        $this->assertSame('moto', Puesto::where('codigo', 'A-1')->first()->tipo);
    }

    #[Test]
    public function sin_codigo_no_guarda(): void
    {
        $this->expectException(ValidationException::class);
        app(CatalogoDePuestos::class)->guardar('   ');
    }

    #[Test]
    public function un_tipo_invalido_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        app(CatalogoDePuestos::class)->guardar('A-1', 'camion');
    }

    #[Test]
    public function un_puesto_sin_tipo_admite_cualquier_vehiculo(): void
    {
        $puesto = app(CatalogoDePuestos::class)->guardar('A-1', '');

        $this->assertNull($puesto->tipo);
        $this->assertTrue($puesto->admite(DatosVehiculo::CARRO));
        $this->assertTrue($puesto->admite(DatosVehiculo::MOTO));
    }

    #[Test]
    public function un_puesto_de_moto_no_admite_un_carro(): void
    {
        $puesto = app(CatalogoDePuestos::class)->guardar('M-1', DatosVehiculo::MOTO);

        $this->assertTrue($puesto->admite(DatosVehiculo::MOTO));
        $this->assertFalse($puesto->admite(DatosVehiculo::CARRO));
    }
}
