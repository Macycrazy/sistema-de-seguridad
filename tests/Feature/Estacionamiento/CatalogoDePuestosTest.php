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
    public function dar_de_alta_un_codigo_que_ya_existe_avisa_y_no_toca_al_que_estaba(): void
    {
        $catalogo = app(CatalogoDePuestos::class);
        $catalogo->guardar('A-1', DatosVehiculo::CARRO, 'Sótano');

        try {
            $catalogo->guardar('A-1', DatosVehiculo::MOTO, 'Frente');
            $this->fail('Un código repetido tiene que avisar, no sobrescribir la plaza que estaba.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Ya hay una plaza', $e->validator->errors()->first('codigo'));
            // Dice DÓNDE está la otra: casi siempre es que se numeró por zona y el número se repite.
            $this->assertStringContainsString('Sótano', $e->validator->errors()->first('codigo'));
        }

        $this->assertSame(1, Puesto::where('codigo', 'A-1')->count());
        $this->assertSame('carro', Puesto::where('codigo', 'A-1')->first()->tipo);
        $this->assertSame('Sótano', Puesto::where('codigo', 'A-1')->first()->zona);
    }

    #[Test]
    public function cambiarle_el_codigo_a_una_plaza_la_renombra_y_no_deja_la_vieja(): void
    {
        $catalogo = app(CatalogoDePuestos::class);
        $plaza = $catalogo->guardar('A-1', DatosVehiculo::CARRO, 'Sótano');

        $catalogo->guardar('B-9', DatosVehiculo::CARRO, 'Sótano', puesto: $plaza);

        $this->assertSame(1, Puesto::count());
        $this->assertSame('B-9', $plaza->fresh()->codigo);
        $this->assertSame(0, Puesto::where('codigo', 'A-1')->count());
    }

    #[Test]
    public function no_se_le_puede_poner_a_una_plaza_el_codigo_de_otra(): void
    {
        $catalogo = app(CatalogoDePuestos::class);
        $primera = $catalogo->guardar('A-1', DatosVehiculo::CARRO, 'Sótano');
        $catalogo->guardar('A-2', DatosVehiculo::MOTO, 'Frente');

        // El fallo que reportaron: esto dejaba A-1 donde estaba y machacaba A-2.
        try {
            $catalogo->guardar('A-2', DatosVehiculo::CARRO, 'Sótano', puesto: $primera);
            $this->fail('Pisar el código de otra plaza tiene que avisar.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Ya hay una plaza', $e->validator->errors()->first('codigo'));
        }

        $this->assertSame(2, Puesto::count());
        $this->assertSame('A-1', $primera->fresh()->codigo);
        $this->assertSame('moto', Puesto::where('codigo', 'A-2')->first()->tipo);
        $this->assertSame('Frente', Puesto::where('codigo', 'A-2')->first()->zona);
    }

    #[Test]
    public function guardar_una_plaza_sin_cambiarle_el_codigo_no_se_estorba_a_si_misma(): void
    {
        $catalogo = app(CatalogoDePuestos::class);
        $plaza = $catalogo->guardar('A-1', DatosVehiculo::CARRO, 'Sótano');

        $catalogo->guardar('A-1', DatosVehiculo::MOTO, 'Frente', puesto: $plaza);

        $this->assertSame('moto', $plaza->fresh()->tipo);
        $this->assertSame('Frente', $plaza->fresh()->zona);
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
