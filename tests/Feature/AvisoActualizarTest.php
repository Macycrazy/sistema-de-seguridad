<?php

namespace Tests\Feature;

use App\Services\MigracionesPendientes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * El aviso de «falta actualizar».
 *
 * Existe por un problema real del equipo: las tres partes tocan las mismas tablas, y quien se
 * baja los cambios de otro sin correr las migraciones se topa con errores de columna que no
 * dicen nada de lo que pasa de verdad.
 */
class AvisoActualizarTest extends TestCase
{
    use RefreshDatabase;

    public function test_con_la_base_al_dia_no_sale_ningun_aviso(): void
    {
        // RefreshDatabase corre todas las migraciones, así que no debe faltar ninguna.
        $this->assertFalse(app(MigracionesPendientes::class)->hay());

        $this->get('/marcar')
            ->assertOk()
            ->assertDontSee('Falta actualizar');
    }

    public function test_si_falta_una_migracion_la_pantalla_lo_avisa(): void
    {
        $this->app['env'] = 'local';
        $this->fingirQueFaltaUna(['2099_01_01_000000_lo_que_sea']);

        $this->get('/marcar')
            ->assertOk()
            ->assertSee('Falta actualizar')
            ->assertSee('php artisan migrate')
            // Y dice cuál falta, para poder buscarla en docs/esquema.md.
            ->assertSee('2099_01_01_000000_lo_que_sea');
    }

    public function test_el_aviso_no_sale_fuera_de_desarrollo(): void
    {
        // En el servidor las migraciones las corre quien despliega: ahí no le sirve a nadie,
        // y enseñarle nombres de archivos a quien está en la puerta no aporta nada.
        $this->app['env'] = 'production';
        $this->fingirQueFaltaUna(['2099_01_01_000000_lo_que_sea']);

        $this->get('/marcar')
            ->assertOk()
            ->assertDontSee('Falta actualizar');
    }

    public function test_sin_tabla_de_migraciones_el_aviso_se_calla_y_no_tumba_la_pantalla(): void
    {
        // Es el caso de quien monta el proyecto por primera vez: la base está en blanco y ni
        // siquiera existe la tabla de migraciones. El aviso es una ayuda, y que falle no puede
        // dejar sin pantalla a nadie.
        Schema::drop('migrations');

        $this->assertSame([], app(MigracionesPendientes::class)->listar());
        $this->assertFalse(app(MigracionesPendientes::class)->hay());

        $this->get('/marcar')->assertOk();
    }

    /** Sustituye el servicio por uno que dice que faltan las que se le pasen. */
    private function fingirQueFaltaUna(array $migraciones): void
    {
        $this->app->instance(MigracionesPendientes::class, new class($migraciones) extends MigracionesPendientes
        {
            public function __construct(private array $lista) {}

            public function listar(): array
            {
                return $this->lista;
            }
        });
    }
}
