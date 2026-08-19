<?php

namespace Tests\Feature\Carnets;

use App\Services\Carnets\Verificador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VerificadorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function probar_dice_ok_cuando_el_carnets_responde(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $r = app(Verificador::class)->probar('http://172.21.140.245:8000');

        $this->assertTrue($r['ok']);
        $this->assertSame(200, $r['http']);
    }

    #[Test]
    public function probar_sin_direccion_avisa(): void
    {
        $r = app(Verificador::class)->probar('   ');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Falta la dirección', $r['mensaje']);
    }

    #[Test]
    public function probar_dice_no_ok_cuando_no_responde(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

        $r = app(Verificador::class)->probar('http://10.0.0.9:8000');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('No respondió', $r['mensaje']);
    }

    #[Test]
    public function verificar_devuelve_el_veredicto_de_carnets(): void
    {
        Http::fake(['*/verificar/qr' => Http::response([
            'estado' => 'activo', 'nombre' => 'ANA PÉREZ', 'cedula' => 'V-12.345.678', 'card_code' => '000123456',
        ], 200)]);

        $r = app(Verificador::class)->verificar('http://carnets:8000', 'http://carnets/Trabajador_abc123');

        $this->assertTrue($r['ok']);
        $this->assertSame('activo', $r['datos']['estado']);
        $this->assertSame('ANA PÉREZ', $r['datos']['nombre']);
    }

    #[Test]
    public function verificar_avisa_si_el_endpoint_pide_sesion(): void
    {
        // 302 = redirección al login: el endpoint todavía exige sesión de navegador.
        Http::fake(['*/verificar/qr' => Http::response('', 302)]);

        $r = app(Verificador::class)->verificar('http://carnets:8000', 'http://carnets/Trabajador_abc123');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('302', $r['mensaje']);
    }
}
