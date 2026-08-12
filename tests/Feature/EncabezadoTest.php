<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El encabezado del sistema.
 *
 * Se prueba sobre todo por una razón: el servidor donde esto va a correr **no tiene salida a
 * Internet**. Una fuente o un icono traído de un CDN no fallaría en la máquina de nadie mientras
 * se desarrolla, y en producción dejaría la pantalla a medias. Esta prueba lo caza antes.
 */
class EncabezadoTest extends TestCase
{
    // La pantalla de marcar consulta cuántos están dentro, así que necesita las tablas.
    use RefreshDatabase;

    /** Las páginas que llevan el encabezado. */
    public static function paginas(): array
    {
        return [
            'inicio' => ['/'],
            'marcar' => ['/marcar'],
            'base visual' => ['/diseno'],
        ];
    }

    #[DataProvider('paginas')]
    public function test_el_logo_del_ciip_sale_en_todas_las_pantallas(string $url): void
    {
        $this->get($url)
            ->assertOk()
            // El BLANCO: el encabezado es azul, y el logo azul no se vería sobre su propio color.
            ->assertSee('imagenes/logo-ciip-blanco.png')
            ->assertSee('Centro Internacional de Inversión Productiva', false);
    }

    #[DataProvider('paginas')]
    public function test_el_encabezado_va_en_el_azul_del_ciip(string $url): void
    {
        $this->get($url)->assertOk()->assertSee('bg-marca');
    }

    #[DataProvider('paginas')]
    public function test_el_titulo_de_la_pestana_dice_ciip(string $url): void
    {
        $this->get($url)->assertOk()->assertSee('· CIIP', false);
    }

    public function test_los_dos_logos_estan_en_el_proyecto(): void
    {
        // Si no vinieran en el repositorio, en el servidor sin Internet no habría logo.
        // El blanco es el del encabezado azul; el azul queda para fondos claros (informes,
        // y la pantalla de ingreso que hará la parte 3).
        $this->assertFileExists(public_path('imagenes/logo-ciip-blanco.png'));
        $this->assertFileExists(public_path('imagenes/logo-ciip.png'));
    }

    #[DataProvider('paginas')]
    public function test_ninguna_pantalla_pide_nada_a_un_servidor_de_fuera(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        preg_match_all('~(?:src|href)=["\'](https?://[^"\']+|//[^"\']+)["\']~i', $html, $coincidencias);

        $propio = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        foreach ($coincidencias[1] as $enlace) {
            $host = parse_url(str_starts_with($enlace, '//') ? "https:{$enlace}" : $enlace, PHP_URL_HOST);

            $this->assertSame(
                $propio,
                $host,
                "«{$enlace}» sale a un servidor de fuera, y el servidor de producción no tiene Internet.",
            );
        }
    }
}
