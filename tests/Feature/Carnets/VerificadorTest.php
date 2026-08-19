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
    public function verificar_devuelve_el_veredicto_activo_con_identidad(): void
    {
        Http::fake(['*/Trabajador_*' => Http::response([
            'activo' => true, 'nacionalidad' => 'V', 'cedula' => '24270727',
            'nombre' => 'JORGE DAVID CASTILLO GARCÍA', 'cargo' => 'ASESOR', 'gerencia' => 'GERENCIA DE PROTOCOLO',
        ], 200)]);

        $r = app(Verificador::class)->verificar('http://carnets:8000', 'http://carnets/Trabajador_abc123def');

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['datos']['activo']);
        $this->assertSame('24270727', $r['datos']['cedula']);
        $this->assertSame('V', $r['datos']['nacionalidad']);
    }

    #[Test]
    public function verificar_devuelve_no_activo(): void
    {
        Http::fake(['*/Trabajador_*' => Http::response(['activo' => false], 200)]);

        $r = app(Verificador::class)->verificar('http://carnets:8000', 'http://carnets/Trabajador_zzz');

        $this->assertTrue($r['ok']);
        $this->assertFalse($r['datos']['activo']);
    }

    #[Test]
    public function usa_la_url_configurada_y_solo_el_token_del_qr(): void
    {
        Http::fake(['*' => Http::response(['activo' => false], 200)]);

        // El QR trae otro host (carnets.viejo); debe llamarse a la URL CONFIGURADA con solo el token.
        app(Verificador::class)->verificar('http://carnets.nuevo:8000', 'http://carnets.viejo/Trabajador_abc123');

        Http::assertSent(fn ($req) => $req->url() === 'http://carnets.nuevo:8000/Trabajador_abc123'
            && $req->hasHeader('Accept', 'application/json'));
    }

    #[Test]
    public function el_token_se_extrae_del_contenido_del_qr(): void
    {
        $v = app(Verificador::class);

        $this->assertSame('abc123def', $v->token('http://carnets/Trabajador_abc123def'));
        $this->assertSame('abc123', $v->token('Trabajador_abc123'));
        $this->assertSame('abc123', $v->token('abc123'));   // ya venía pelado
    }
}
