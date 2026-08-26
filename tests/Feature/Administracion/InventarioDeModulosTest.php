<?php

namespace Tests\Feature\Administracion;

use App\Models\Oficina;
use App\Models\Pase;
use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Las tarjetas de administración dicen cuánto tiene cargado cada módulo.
 *
 * Un catálogo vacío y uno lleno se veían igual desde la portada, y eso engaña justo el primer día:
 * quien entra a un módulo en blanco no sabe si está roto, si le falta un permiso o si simplemente
 * nadie ha cargado nada.
 */
class InventarioDeModulosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_modulo_sin_cargar_lo_dice(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $this->get(route('administracion'))->assertOk()->assertSee('Sin cargar');
    }

    #[Test]
    public function con_datos_dice_cuantos(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        // Las oficinas vienen sembradas por la migración del edificio, así que se cuentan las que
        // haya: lo que se prueba es que el número sale, no cuántas hay.
        Pase::create(['codigo' => 'V-01']);
        $oficinas = Oficina::count();

        $this->get(route('administracion'))
            ->assertOk()
            ->assertSee('1 pase')        // en singular, con su etiqueta bien concordada
            ->assertSee(number_format($oficinas).' oficina');
    }
}
