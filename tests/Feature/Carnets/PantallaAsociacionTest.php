<?php

namespace Tests\Feature\Carnets;

use App\Livewire\Asociacion\Carnets;
use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaAsociacionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_quien_gestiona_ajustes_entra(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::SUPERVISOR]));
        $this->get(route('asociacion'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $this->get(route('asociacion'))->assertOk();
    }

    #[Test]
    public function el_boton_prueba_la_conexion(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(Carnets::class)
            ->set('url', 'http://172.21.140.245:8000')
            ->call('probar')
            ->assertSee('respondió');
    }

    #[Test]
    public function verificar_sin_qr_pide_pegarlo(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(Carnets::class)
            ->set('url', 'http://carnets:8000')
            ->set('qr', '')
            ->call('verificar')
            ->assertSee('Pega primero');
    }
}
