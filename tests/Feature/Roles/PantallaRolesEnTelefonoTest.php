<?php

namespace Tests\Feature\Roles;

use App\Livewire\Roles\PermisosPorRol;
use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La matriz de permisos, vista desde un teléfono.
 *
 * Es una tabla de permisos por roles, y en una pantalla estrecha se estropeaba: la explicación de
 * cada permiso ocupaba tres o cuatro renglones, estiraba la fila entera y dejaba unas celdas
 * enormes con un cuadradito de veinte píxeles en medio, difícil de acertar con el dedo.
 *
 * Esto mira el marcado y no el aspecto —no hay navegador aquí— pero fija las tres decisiones que
 * lo arreglan, para que no se deshagan sin querer.
 */
class PantallaRolesEnTelefonoTest extends TestCase
{
    use RefreshDatabase;

    private function html(): string
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        return Livewire::test(PermisosPorRol::class)->assertOk()->html();
    }

    #[Test]
    public function la_explicacion_del_permiso_no_se_muestra_en_el_telefono_pero_no_se_pierde(): void
    {
        $html = $this->html();

        // Sigue en la página —entera en pantalla grande, y en el «title» del nombre— pero oculta
        // mientras la pantalla sea estrecha.
        $this->assertStringContainsString('hidden text-xs text-slate-500 sm:block', $html);
        $this->assertStringContainsString('title=', $html);
    }

    #[Test]
    public function la_casilla_se_toca_en_toda_la_celda_y_no_solo_en_el_cuadradito(): void
    {
        $this->assertStringContainsString('<label class="flex cursor-pointer items-center justify-center', $this->html());
    }

    #[Test]
    public function la_columna_del_permiso_se_queda_fija_al_desplazar(): void
    {
        // La tabla es más ancha que un teléfono: sin esto se marcan casillas sin ver de qué son.
        $this->assertStringContainsString('sticky left-0', $this->html());
    }
}
