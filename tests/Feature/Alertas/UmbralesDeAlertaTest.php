<?php

namespace Tests\Feature\Alertas;

use App\Models\Parametro;
use App\Services\Alertas\UmbralesDeAlerta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UmbralesDeAlertaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sin_nada_en_la_base_manda_el_valor_por_omision(): void
    {
        $servicio = app(UmbralesDeAlerta::class);

        $this->assertSame(12, $servicio->horasPermanencia());
        $this->assertSame(0, $servicio->aforo());
    }

    #[Test]
    public function guardar_persiste_y_manda_sobre_el_default(): void
    {
        app(UmbralesDeAlerta::class)->guardar('alerta_horas_permanencia', 8);

        $this->assertDatabaseHas('parametros', ['clave' => 'alerta_horas_permanencia', 'valor' => 8]);
        $this->assertSame(8, app(UmbralesDeAlerta::class)->horasPermanencia());
    }

    #[Test]
    public function un_valor_fuera_de_limites_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);

        app(UmbralesDeAlerta::class)->guardar('alerta_horas_permanencia', 100);   // máximo 48
    }

    #[Test]
    public function una_clave_desconocida_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);

        app(UmbralesDeAlerta::class)->guardar('alerta_inventada', 5);
        $this->assertSame(0, Parametro::count());
    }
}
