<?php

namespace Tests\Feature\Ajustes;

use App\Models\Parametro;
use App\Services\Marcaje;
use App\Services\ReglasDeTiempo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReglasDeTiempoTest extends TestCase
{
    use RefreshDatabase;

    private function reglas(): ReglasDeTiempo
    {
        return app(ReglasDeTiempo::class);
    }

    #[Test]
    public function la_migracion_sembro_los_valores_de_las_constantes(): void
    {
        $this->assertSame(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA, $this->reglas()->minutosEntreEntradaYSalida());
        $this->assertSame(Marcaje::SEGUNDOS_ANTIDUPLICADO, $this->reglas()->segundosAntiduplicado());
    }

    #[Test]
    public function si_la_tabla_esta_vacia_manda_el_default_del_codigo(): void
    {
        Parametro::query()->delete();

        $this->assertSame(Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA, $this->reglas()->minutosEntreEntradaYSalida());
    }

    #[Test]
    public function guardar_cambia_el_valor(): void
    {
        $this->reglas()->guardar('minutos_entre_salida_y_entrada', 30);

        $this->assertSame(30, app(ReglasDeTiempo::class)->minutosEntreSalidaYEntrada());
    }

    #[Test]
    public function un_valor_fuera_de_los_limites_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        $this->reglas()->guardar('minutos_entre_entrada_y_salida', 99999);
    }

    #[Test]
    public function un_parametro_desconocido_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);
        $this->reglas()->guardar('lo_que_sea', 5);
    }

    #[Test]
    public function marcaje_usa_el_valor_ajustado_no_la_constante(): void
    {
        // Se sube a 60 el plazo entre la entrada y su salida; Marcaje, que lee las reglas, lo refleja.
        $this->reglas()->guardar('minutos_entre_entrada_y_salida', 60);

        $this->assertSame(60, app(Marcaje::class)->minutosEntreEntradaYSalida());
    }
}
