<?php

namespace Tests\Feature\Visitas;

use App\Livewire\Visitas\Agenda;
use App\Models\User;
use App\Models\VisitaEsperada;
use App\Usuarios\Rol;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaVisitasTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_vigilante_no_agenda_pero_el_supervisor_si(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
        $this->get(route('visitas'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $this->get(route('visitas'))->assertOk();
    }

    #[Test]
    public function agendar_desde_la_pantalla_la_lista_en_su_dia(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));

        Livewire::test(Agenda::class)
            ->call('abrirAlta')
            ->set('nombre', 'JUAN PÉREZ')
            ->set('fechaEsperada', CarbonImmutable::today()->toDateString())
            ->call('agendar')
            ->assertHasNoErrors()
            ->assertSee('JUAN PÉREZ');

        $this->assertDatabaseHas('visitas_esperadas', ['nombre' => 'JUAN PÉREZ', 'estado' => VisitaEsperada::ESPERADA]);
    }

    #[Test]
    public function marcar_la_llegada_desde_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $visita = VisitaEsperada::create(['nombre' => 'ANA', 'fecha_esperada' => CarbonImmutable::today(), 'estado' => VisitaEsperada::ESPERADA]);

        Livewire::test(Agenda::class)
            ->call('marcarLlegada', $visita->id);

        $this->assertSame(VisitaEsperada::LLEGO, $visita->fresh()->estado);
    }
}
