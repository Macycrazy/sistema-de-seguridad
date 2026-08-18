<?php

namespace Tests\Feature\Retencion;

use App\Services\Retencion\RetencionDeDatos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RetencionDeDatosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function de_fabrica_la_retencion_esta_desactivada(): void
    {
        $servicio = app(RetencionDeDatos::class);

        $this->assertSame(0, $servicio->mesesMovimientos());
        $this->assertSame(0, $servicio->mesesBitacora());
    }

    #[Test]
    public function guardar_persiste_el_periodo(): void
    {
        app(RetencionDeDatos::class)->guardar('retencion_movimientos_meses', 24);

        $this->assertDatabaseHas('parametros', ['clave' => 'retencion_movimientos_meses', 'valor' => 24]);
        $this->assertSame(24, app(RetencionDeDatos::class)->mesesMovimientos());
    }

    #[Test]
    public function un_periodo_fuera_de_limites_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        app(RetencionDeDatos::class)->guardar('retencion_movimientos_meses', 999);   // máximo 120
    }

    #[Test]
    public function una_clave_desconocida_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        app(RetencionDeDatos::class)->guardar('retencion_inventada', 5);
    }
}
