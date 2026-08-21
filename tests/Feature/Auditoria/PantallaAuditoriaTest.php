<?php

namespace Tests\Feature\Auditoria;

use App\Livewire\Auditoria\ListaDeBitacora;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function solo_quien_tiene_ver_auditoria_abre_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::supervisor()]));
        $this->get(route('auditoria'))->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));
        $this->get(route('auditoria'))->assertOk();
    }

    #[Test]
    public function la_pantalla_muestra_las_entradas_y_filtra_por_accion(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));
        // Se afirma sobre los valores de «sobre», que solo salen en las filas; las etiquetas de
        // acción también viven en el desplegable del filtro y no sirven para el assertDontSee.
        app(Auditoria::class)->anota(Auditoria::EXPORTO_REGISTRO, 'fue-un-export');
        app(Auditoria::class)->anota(Auditoria::CAMBIO_OFICINAS, 'fue-una-oficina');

        Livewire::test(ListaDeBitacora::class)
            ->assertSee('fue-un-export')
            ->assertSee('fue-una-oficina')
            ->set('accion', Auditoria::EXPORTO_REGISTRO)
            ->assertSee('fue-un-export')
            ->assertDontSee('fue-una-oficina');
    }
}
