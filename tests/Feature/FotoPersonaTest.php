<?php

namespace Tests\Feature;

use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La ruta que entrega las fotos.
 *
 * Importa porque es la única puerta por la que sale la cara de una persona: las fotos no están en
 * ninguna carpeta pública. Aquí se comprueba que la puerta solo abra para lo que debe.
 */
class FotoPersonaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La ruta de las fotos está detrás del ingreso desde la parte 3. El permiso por rol
        // —quién puede ver la cara de quién— es el bloque B y aún no está.
        $this->entrandoComo();

        // Disco de mentira: las pruebas no escriben en storage/app/private de verdad.
        Storage::fake('local');
    }

    private function trabajador(array $atributos = []): Persona
    {
        return Persona::create(array_merge([
            'cedula' => '12345678',
            'tipo' => Persona::TRABAJADOR,
            'nombre' => 'Ana Rodríguez Peña',
            'dependencia' => 'Recursos Humanos',
            'activo' => true,
        ], $atributos));
    }

    public function test_la_foto_se_entrega_cuando_existe(): void
    {
        $persona = $this->trabajador(['foto_ruta' => 'fotos/12345678.jpg']);
        Storage::disk('local')->put('fotos/12345678.jpg', 'contenido-de-la-foto');

        $respuesta = $this->get(route('persona.foto', $persona))->assertOk();

        // Lo que importa de la cabecera, sin atarse a su orden ni a las directivas que
        // añada el framework: la cara de alguien no se guarda en ninguna caché.
        $cache = $respuesta->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);
    }

    public function test_sin_foto_da_404(): void
    {
        $persona = $this->trabajador(['foto_ruta' => null]);

        $this->get(route('persona.foto', $persona))->assertNotFound();
    }

    public function test_si_la_ficha_apunta_a_un_archivo_que_no_esta_da_404(): void
    {
        // Pasa de verdad: la ficha viene del sistema de carnets y la imagen puede no haber llegado.
        $persona = $this->trabajador(['foto_ruta' => 'fotos/no-existe.jpg']);

        $this->get(route('persona.foto', $persona))->assertNotFound();
    }

    public function test_la_ruta_no_sirve_para_leer_otros_archivos_del_servidor(): void
    {
        Storage::disk('local')->put('secreto.txt', 'esto no se puede servir');

        // Aunque alguien lograra escribir otra ruta en la base, la puerta no la acepta.
        foreach (['secreto.txt', '../.env', 'fotos/../secreto.txt', '/etc/passwd'] as $ruta) {
            $persona = $this->trabajador([
                'cedula' => (string) random_int(100000, 999999),
                'foto_ruta' => $ruta,
            ]);

            $this->get(route('persona.foto', $persona))
                ->assertNotFound("La ruta «{$ruta}» no debería haberse servido");
        }
    }

    public function test_una_persona_con_foto_lo_dice_y_sin_ella_no(): void
    {
        $conFoto = $this->trabajador(['cedula' => '11111111', 'foto_ruta' => 'fotos/11111111.jpg']);
        Storage::disk('local')->put('fotos/11111111.jpg', 'foto');

        $sinFoto = $this->trabajador(['cedula' => '22222222', 'foto_ruta' => null]);

        $this->assertTrue($conFoto->tieneFoto());
        $this->assertFalse($sinFoto->tieneFoto());
    }

    public function test_las_iniciales_sirven_de_respaldo_cuando_no_hay_foto(): void
    {
        $persona = $this->trabajador(['nombre' => 'Ana Rodríguez Peña']);

        $this->assertSame('AR', $persona->iniciales());
    }
}
