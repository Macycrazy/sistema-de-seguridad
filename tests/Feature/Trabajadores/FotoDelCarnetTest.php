<?php

namespace Tests\Feature\Trabajadores;

use App\Services\Carnets\FotoDelCarnet;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FotoDelCarnetTest extends TestCase
{
    private FotoDelCarnet $fotos;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->fotos = app(FotoDelCarnet::class);
    }

    #[Test]
    public function trae_la_foto_de_una_carpeta_del_disco_y_la_guarda(): void
    {
        // Un carnets en la misma máquina: la foto es un archivo en su carpeta.
        $carpeta = storage_path('framework/testing/carnets-'.uniqid());
        File::ensureDirectoryExists($carpeta);
        File::put($carpeta.'/12345678.jpg', 'BYTES-DE-LA-FOTO');
        config(['carnets.fotos' => $carpeta]);

        $ruta = $this->fotos->traer('12.345.678');

        $this->assertSame('fotos/12345678.jpg', $ruta);
        Storage::disk('local')->assertExists('fotos/12345678.jpg');
        $this->assertSame('BYTES-DE-LA-FOTO', Storage::disk('local')->get('fotos/12345678.jpg'));

        File::deleteDirectory($carpeta);
    }

    #[Test]
    public function trae_la_foto_de_un_carnets_en_red_por_http(): void
    {
        config(['carnets.fotos' => 'http://172.17.1.23:8000/imgs/usuarios']);
        Http::fake(['*/12345678.jpg' => Http::response('FOTO-POR-HTTP', 200)]);

        $ruta = $this->fotos->traer('12345678');

        $this->assertSame('fotos/12345678.jpg', $ruta);
        $this->assertSame('FOTO-POR-HTTP', Storage::disk('local')->get('fotos/12345678.jpg'));
    }

    #[Test]
    public function si_no_hay_foto_devuelve_null_sin_reventar(): void
    {
        $carpeta = storage_path('framework/testing/carnets-vacio-'.uniqid());
        File::ensureDirectoryExists($carpeta);
        config(['carnets.fotos' => $carpeta]);

        $this->assertNull($this->fotos->traer('12345678'));

        File::deleteDirectory($carpeta);
    }

    #[Test]
    public function un_carnets_caido_no_revienta_solo_devuelve_null(): void
    {
        config(['carnets.fotos' => 'http://172.17.1.23:8000/imgs/usuarios']);
        Http::fake(fn () => throw new ConnectionException('sin conexión'));

        $this->assertNull($this->fotos->traer('12345678'));
    }

    #[Test]
    public function sin_origen_configurado_no_hace_nada(): void
    {
        config(['carnets.fotos' => null]);

        $this->assertNull($this->fotos->traer('12345678'));
    }
}
