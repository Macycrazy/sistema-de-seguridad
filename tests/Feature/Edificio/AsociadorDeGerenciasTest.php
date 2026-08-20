<?php

namespace Tests\Feature\Edificio;

use App\Models\Oficina;
use App\Models\Persona;
use App\Services\Edificio\AsociadorDeGerencias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsociadorDeGerenciasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // La migración siembra oficinas desde la config; partimos de un catálogo limpio.
        Oficina::query()->delete();
    }

    private function trabajador(string $cedula, string $dependencia, string $piso): void
    {
        Persona::create([
            'cedula' => $cedula, 'tipo' => Persona::TRABAJADOR, 'nombre' => 'P '.$cedula,
            'dependencia' => $dependencia, 'piso' => $piso, 'activo' => true,
        ]);
    }

    #[Test]
    public function asocia_cada_piso_a_la_gerencia_de_su_gente(): void
    {
        $this->trabajador('11111111', 'GESTIÓN HUMANA', '4-1');
        $this->trabajador('22222222', 'TECNOLOGÍA', '2-1');

        $r = app(AsociadorDeGerencias::class)->aplicar();

        $this->assertSame(2, $r['creadas']);
        $this->assertDatabaseHas('oficinas', ['codigo' => '4-1', 'gerencia' => 'GESTIÓN HUMANA']);
        $this->assertDatabaseHas('oficinas', ['codigo' => '2-1', 'gerencia' => 'TECNOLOGÍA']);
    }

    #[Test]
    public function en_un_piso_con_varias_gerencias_gana_la_mayoria(): void
    {
        $this->trabajador('1', 'VENAPP', '7');
        $this->trabajador('2', 'VENAPP', '7');
        $this->trabajador('3', 'CIIP', '7');   // minoría

        $plan = app(AsociadorDeGerencias::class)->plan();

        $this->assertTrue($plan['7']['conflicto']);
        $this->assertSame('VENAPP', $plan['7']['gerencia']);
    }

    #[Test]
    public function respeta_las_oficinas_con_gerencia_puesta_a_mano(): void
    {
        Oficina::create(['codigo' => '4-1', 'gerencia' => 'PRESIDENCIA', 'orden' => 1]);
        $this->trabajador('11111111', 'GESTIÓN HUMANA', '4-1');

        $r = app(AsociadorDeGerencias::class)->aplicar();

        $this->assertSame(1, $r['saltadas']);
        $this->assertDatabaseHas('oficinas', ['codigo' => '4-1', 'gerencia' => 'PRESIDENCIA']);
    }

    #[Test]
    public function simular_no_escribe_nada(): void
    {
        $this->trabajador('11111111', 'GESTIÓN HUMANA', '4-1');

        $r = app(AsociadorDeGerencias::class)->aplicar(simular: true);

        $this->assertSame(1, $r['creadas']);
        $this->assertDatabaseMissing('oficinas', ['codigo' => '4-1']);
    }
}
