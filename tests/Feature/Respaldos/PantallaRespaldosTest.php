<?php

namespace Tests\Feature\Respaldos;

use App\Livewire\Respaldos\Panel;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Services\Respaldos\Respaldos;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaRespaldosTest extends TestCase
{
    use RefreshDatabase;

    private function servicioFalso(): void
    {
        $this->app->instance(Respaldos::class, new class extends Respaldos
        {
            protected function volcar(string $destino): void
            {
                file_put_contents($destino, "-- respaldo de prueba\n");
            }
        });
    }

    #[Test]
    public function solo_el_administrador_entra(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));
        $this->get(route('respaldos'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $this->get(route('respaldos'))->assertOk();
    }

    #[Test]
    public function crear_desde_la_pantalla_lo_lista_y_lo_audita(): void
    {
        Storage::fake('local');
        $this->servicioFalso();
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(Panel::class)
            ->call('crear')
            ->assertSee('.sql');

        $this->assertSame(1, app(Respaldos::class)->listar()->count());
        $this->assertDatabaseHas('bitacora', ['accion' => Auditoria::RESPALDO]);
    }
}
