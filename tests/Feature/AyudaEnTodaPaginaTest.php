<?php

namespace Tests\Feature;

use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El «?» de la ayuda lo mueve Alpine, y Alpine viaja dentro del paquete de Livewire.
 *
 * Livewire solo inyecta ese paquete cuando la página monta algún componente suyo, así que las
 * pantallas que son puro Blade —Administración e Inicio, que son tarjetas y enlaces— se quedaban
 * sin él, y con él sin Alpine: su «?» era un botón que no abría nada. El layout ahora lo pone a
 * mano; esto vigila que siga puesto.
 */
class AyudaEnTodaPaginaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function pantallasSinComponentes(): array
    {
        return [
            'administración' => ['administracion'],
            'inicio' => ['inicio'],
        ];
    }

    #[Test]
    #[DataProvider('pantallasSinComponentes')]
    public function una_pantalla_sin_componentes_livewire_igual_carga_alpine(string $ruta): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        // Livewire recuerda si ya sirvió sus scripts, y esa marca es del proceso, no de la
        // petición: en la vida real cada visita empieza limpia, y aquí hay que dejarla igual.
        app(FrontendAssets::class)->hasRenderedScripts = false;

        $html = $this->get(route($ruta))->assertOk()->getContent();

        $this->assertStringContainsString('x-data', $html, "«{$ruta}» debería traer la ayuda.");
        $this->assertStringContainsString(
            'livewire.js',
            $html,
            "«{$ruta}» no carga Livewire, así que no hay Alpine y su «?» no abre.",
        );
    }
}
