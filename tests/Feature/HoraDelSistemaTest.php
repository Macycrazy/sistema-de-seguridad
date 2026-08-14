<?php

namespace Tests\Feature;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Marcaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La hora del sistema es la de Venezuela.
 *
 * Esto se prueba porque ya falló una vez: config/app.php traía «UTC» escrito a mano e ignoraba el
 * APP_TIMEZONE del .env, así que todo se guardaba cuatro horas adelantado. En un sistema que
 * registra a qué hora entra y sale cada persona, eso no es un detalle:
 *
 *   - las horas del registro (parte 2) saldrían todas corridas;
 *   - y un movimiento a las 21:00 de Caracas caería en la FECHA del día siguiente, así que el
 *     reporte diario partiría los turnos de noche en dos.
 */
class HoraDelSistemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_zona_horaria_es_la_de_venezuela(): void
    {
        $this->assertSame('America/Caracas', config('app.timezone'));
    }

    public function test_un_movimiento_se_guarda_con_la_hora_de_venezuela(): void
    {
        $persona = Persona::create([
            'cedula' => '12345678',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'Ana Rodríguez Peña',
            'dependencia' => 'Recursos Humanos',
            'activo' => true,
        ]);

        $movimiento = app(Marcaje::class)->registrar($persona, Movimiento::ENTRADA);

        $this->assertSame(
            'America/Caracas',
            $movimiento->ocurrio_en->timezone->getName(),
            'El movimiento no quedó en hora de Venezuela.',
        );
    }

    public function test_la_hora_guardada_no_va_adelantada_respecto_a_venezuela(): void
    {
        // La comparación es contra la hora de Caracas calculada aparte, no contra la del
        // framework: si el framework estuviera mal configurado, compararlo consigo mismo no
        // detectaría nada.
        $caracas = new \DateTime('now', new \DateTimeZone('America/Caracas'));

        $this->assertSame(
            $caracas->format('Y-m-d H'),
            now()->format('Y-m-d H'),
            'La hora del sistema no coincide con la de Venezuela.',
        );
    }
}
