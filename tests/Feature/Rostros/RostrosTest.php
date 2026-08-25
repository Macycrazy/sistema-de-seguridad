<?php

namespace Tests\Feature\Rostros;

use App\Livewire\Marcar;
use App\Livewire\Rostros\Indice;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Rostro;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Services\Rostros\Rostros;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El índice de rostros y lo que la puerta hace con él.
 *
 * El reconocimiento en sí ocurre en el navegador y no se puede probar desde aquí; lo que sí se
 * prueba —y es lo que importa— es que el servidor guarda solo lo que debe, que la galería no
 * lleva de más, y sobre todo que reconocer una cara NO marca a nadie.
 */
class RostrosTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, float> Un descriptor cualquiera, con la forma correcta. */
    private function descriptor(float $semilla = 0.1): array
    {
        return array_fill(0, Rostro::LARGO, $semilla);
    }

    private function trabajador(string $cedula = '12345678', string $nombre = 'ANA RODRÍGUEZ'): Persona
    {
        return Persona::create([
            'cedula' => $cedula,
            'tipo' => Persona::TRABAJADOR,
            'nombre' => $nombre,
            'dependencia' => 'RECURSOS HUMANOS',
            'activo' => true,
        ]);
    }

    #[Test]
    public function guarda_el_descriptor_que_calculo_el_navegador(): void
    {
        $ana = $this->trabajador();

        app(Rostros::class)->guardar($ana, $this->descriptor());

        $this->assertDatabaseHas('rostros', ['persona_id' => $ana->id, 'origen' => 'carnet']);
        $this->assertCount(Rostro::LARGO, Rostro::first()->descriptor);
    }

    #[Test]
    public function un_descriptor_con_forma_rara_no_se_guarda(): void
    {
        // Llega por la red desde el navegador: puede venir cualquier cosa.
        $ana = $this->trabajador();

        $this->expectException(ValidationException::class);
        app(Rostros::class)->guardar($ana, [1.0, 2.0, 3.0]);
    }

    #[Test]
    public function reindexar_pisa_el_rostro_anterior_y_no_crea_otro(): void
    {
        $ana = $this->trabajador();

        app(Rostros::class)->guardar($ana, $this->descriptor(0.1));
        app(Rostros::class)->guardar($ana, $this->descriptor(0.9));

        $this->assertSame(1, Rostro::count());
        $this->assertSame(0.9, Rostro::first()->descriptor[0]);
    }

    #[Test]
    public function la_galeria_lleva_lo_justo_y_ninguna_foto(): void
    {
        $ana = $this->trabajador();
        app(Rostros::class)->guardar($ana, $this->descriptor());

        $galeria = app(Rostros::class)->galeria();

        $this->assertCount(1, $galeria);
        $this->assertSame(['cedula', 'nombre', 'descriptores'], array_keys($galeria[0]));
        $this->assertSame($ana->cedula, $galeria[0]['cedula']);

        // Las muestras van juntas por persona: al comparar se usa la que mejor case.
        $this->assertCount(1, $galeria[0]['descriptores']);
    }

    #[Test]
    public function una_persona_puede_tener_varias_caras_y_van_juntas(): void
    {
        // La del carnet es de hace años: cada muestra nueva es la misma cara con otra luz, otras
        // gafas, otro día. Al comparar se usa la que mejor case.
        $ana = $this->trabajador();

        app(Rostros::class)->guardar($ana, $this->descriptor(0.1), Rostro::DEL_CARNET);
        app(Rostros::class)->guardar($ana, $this->descriptor(0.2), Rostro::DE_LA_CAMARA);
        app(Rostros::class)->guardar($ana, $this->descriptor(0.3), Rostro::DE_LA_CAMARA);

        $galeria = app(Rostros::class)->galeria();

        $this->assertCount(1, $galeria, 'Una entrada por persona…');
        $this->assertCount(3, $galeria[0]['descriptores'], '…con sus tres caras dentro.');
        $this->assertSame(3, app(Rostros::class)->muestrasDe($ana)->count());
    }

    #[Test]
    public function el_tope_de_muestras_se_puede_subir_desde_los_ajustes(): void
    {
        // Seis no es una ley: lo que limita son el peso de la galería y los falsos positivos, y
        // quien administra puede decidir dónde está su punto.
        $ana = $this->trabajador();

        app(Rostros::class)->fijarMaxMuestras(10);
        $this->assertSame(10, app(Rostros::class)->maxMuestras());

        foreach (range(1, 12) as $i) {
            app(Rostros::class)->guardar($ana, $this->descriptor($i / 100), Rostro::DE_LA_CAMARA);
        }

        $this->assertSame(10, app(Rostros::class)->muestrasDe($ana)->count());

        // Y no se deja pasar del tope duro.
        app(Rostros::class)->fijarMaxMuestras(999);
        $this->assertSame(Rostros::TOPE_MUESTRAS, app(Rostros::class)->maxMuestras());
    }

    #[Test]
    public function lo_estricto_que_se_pone_la_puerta_se_ajusta_y_tiene_topes(): void
    {
        // Confundir a dos personas es lo peor que puede hacer esto, y el punto bueno depende de
        // las fotos que haya. Pero no se puede dejar ni inservible ni crédula.
        $rostros = app(Rostros::class);

        $rostros->fijarAjustes(0.38, 0.10, 3);

        $this->assertSame(0.38, $rostros->ajustes()['umbral']);
        $this->assertSame(0.10, $rostros->ajustes()['margen']);
        $this->assertSame(3, $rostros->ajustes()['confirmaciones']);

        // Un umbral altísimo reconocería a cualquiera como cualquiera.
        $rostros->fijarAjustes(9.9, 9.9, 99);

        $this->assertSame(0.70, $rostros->ajustes()['umbral']);
        $this->assertSame(0.30, $rostros->ajustes()['margen']);
        $this->assertSame(5, $rostros->ajustes()['confirmaciones']);
    }

    #[Test]
    public function la_galeria_va_redondeada_para_que_no_pese_de_mas(): void
    {
        // Viaja entera al navegador cada vez que se abre la cámara. Las distancias entre caras se
        // juegan en el segundo y el tercer decimal, así que el cuarto no cambia ninguna decisión.
        $ana = $this->trabajador();
        app(Rostros::class)->guardar($ana, array_fill(0, Rostro::LARGO, 0.123456789));

        $numero = app(Rostros::class)->galeria()[0]['descriptores'][0][0];

        $this->assertSame(0.1235, $numero);
    }

    #[Test]
    public function reindexar_sustituye_la_del_carnet_y_no_toca_las_de_la_camara(): void
    {
        // Reindexar es «vuelve a mirar su foto», no «olvida lo que aprendiste de él».
        $ana = $this->trabajador();

        app(Rostros::class)->guardar($ana, $this->descriptor(0.5), Rostro::DE_LA_CAMARA);
        app(Rostros::class)->guardar($ana, $this->descriptor(0.1), Rostro::DEL_CARNET);
        app(Rostros::class)->guardar($ana, $this->descriptor(0.9), Rostro::DEL_CARNET);

        $muestras = app(Rostros::class)->muestrasDe($ana);

        $this->assertCount(2, $muestras, 'La del carnet es una sola; la de la cámara sigue.');
        $this->assertSame(1, $muestras->where('origen', Rostro::DEL_CARNET)->count());
        $this->assertSame(0.9, $muestras->firstWhere('origen', Rostro::DEL_CARNET)->descriptor[0]);
    }

    #[Test]
    public function las_muestras_de_camara_tienen_techo_y_se_tiran_las_mas_viejas(): void
    {
        // Cada una cuesta trabajo por cuadro de vídeo y peso al descargar la galería.
        $ana = $this->trabajador();

        app(Rostros::class)->guardar($ana, $this->descriptor(0.1), Rostro::DEL_CARNET);

        foreach (range(1, Rostros::MAX_MUESTRAS_POR_OMISION + 3) as $i) {
            app(Rostros::class)->guardar($ana, $this->descriptor($i / 100), Rostro::DE_LA_CAMARA);
        }

        $muestras = app(Rostros::class)->muestrasDe($ana);

        $this->assertSame(Rostros::MAX_MUESTRAS_POR_OMISION, $muestras->where('origen', Rostro::DE_LA_CAMARA)->count());
        $this->assertSame(1, $muestras->where('origen', Rostro::DEL_CARNET)->count(), 'La del carnet no se tira nunca.');
    }

    #[Test]
    public function quien_se_desactiva_desaparece_de_la_galeria(): void
    {
        $ana = $this->trabajador();
        app(Rostros::class)->guardar($ana, $this->descriptor());

        $ana->update(['activo' => false]);

        $this->assertCount(0, app(Rostros::class)->galeria());
    }

    #[Test]
    public function a_los_visitantes_no_se_les_indexa(): void
    {
        // Su foto no está en ninguna parte, y guardarles la cara sería recoger biometría de quien
        // solo viene de visita.
        Persona::create([
            'cedula' => '99999999', 'tipo' => Persona::INVITADO,
            'nombre' => 'VISITA', 'motivo' => 'X', 'activo' => true,
        ]);

        $this->assertCount(0, app(Rostros::class)->indexables());
    }

    #[Test]
    public function el_rostro_se_va_con_la_persona(): void
    {
        // Es un dato personal: si la persona se borra, su cara no puede quedarse.
        $ana = $this->trabajador();
        app(Rostros::class)->guardar($ana, $this->descriptor());

        $ana->delete();

        $this->assertSame(0, Rostro::count());
    }

    #[Test]
    public function el_indice_se_puede_vaciar_entero(): void
    {
        // Es la salida si esto se decide no usar.
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        app(Rostros::class)->guardar($this->trabajador(), $this->descriptor());
        app(Rostros::class)->guardar($this->trabajador('87654321', 'LUIS'), $this->descriptor());

        Livewire::test(Indice::class)->call('vaciar')->assertHasNoErrors();

        $this->assertSame(0, Rostro::count());
        $this->assertDatabaseHas('bitacora', ['accion' => Auditoria::BORRO_ROSTROS]);
    }

    #[Test]
    public function ningun_boton_se_queda_con_una_directiva_de_blade_sin_evaluar(): void
    {
        /*
         * El fallo que esto vigila: Blade NO interpreta sus directivas dentro de los atributos de
         * un componente. Un «x-on:click="indexar(@json(...))"» en un <x-boton> viaja tal cual al
         * navegador, Alpine recibe algo que no es JavaScript, y el botón deja de hacer nada sin
         * decir por qué —desde el servidor todo parece correcto—.
         *
         * Por eso las listas van en el x-data del div, que sí es HTML normal.
         */
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));
        $this->trabajador();

        $html = Livewire::test(Indice::class)->html();

        $this->assertStringNotContainsString('@json', $html, 'Quedó un @json sin evaluar en la pantalla.');
        $this->assertStringContainsString("indexar('pendientes')", $html);

        // Y el x-data va limpio: los datos se piden por Livewire. Un JSON aquí rompería el
        // atributo —sus comillas chocan con las del valor— y Alpine se queja de un paréntesis.
        $this->assertStringContainsString('x-data="indiceDeRostros($wire)"', $html);
    }

    #[Test]
    public function la_puerta_tampoco_deja_directivas_sin_evaluar(): void
    {
        $this->entrandoComo();
        app(Rostros::class)->guardar($this->trabajador(), $this->descriptor());

        $html = Livewire::test(Marcar::class)->html();

        $this->assertStringNotContainsString('@json', $html);
        $this->assertStringContainsString('x-data="rostroEnLaPuerta($wire)"', $html);
    }

    #[Test]
    public function reconocer_una_cara_deja_la_ficha_pero_n_o_marca_a_nadie(): void
    {
        // Lo más importante de todo el módulo: propone, no decide. Un parecido no es una
        // identificación, y quien marca es el vigilante mirando la foto.
        $this->entrandoComo();

        $ana = $this->trabajador();
        app(Rostros::class)->guardar($ana, $this->descriptor());

        Livewire::test(Marcar::class)
            ->call('rostroReconocido', $ana->cedula, 0.31)
            ->assertHasNoErrors()
            ->assertSet('cedula', $ana->cedula)
            ->assertSee('ANA RODRÍGUEZ');

        $this->assertSame(0, Movimiento::count(), 'Reconocer no puede marcar.');
        $this->assertDatabaseHas('bitacora', [
            'accion' => Auditoria::IDENTIFICO_POR_ROSTRO,
            'sobre' => $ana->cedula,
        ]);
    }

    #[Test]
    public function volver_a_indexar_ofrece_a_todos_y_no_solo_a_los_que_faltan(): void
    {
        // La foto manda y puede cambiar: si en carnets le ponen otra a alguien ya indexado, hay
        // que poder volver a mirarla sin borrar el índice entero.
        $this->actingAs(User::factory()->create(['rol' => Rol::administrador()]));

        $ana = $this->trabajador();
        $this->trabajador('87654321', 'LUIS GÓMEZ');
        app(Rostros::class)->guardar($ana, $this->descriptor());

        $componente = Livewire::test(Indice::class);

        $this->assertCount(1, $componente->instance()->pendientes, 'Pendiente solo Luis.');
        $this->assertCount(2, $componente->instance()->todos, 'Para volver a mirar, los dos.');

        // La foto va con la hora pegada, o el navegador reusaría la que ya tenía guardada.
        $this->assertStringContainsString('?v=', $componente->instance()->todos[0]['foto']);
    }

    #[Test]
    public function una_cedula_reconocida_que_ya_no_existe_lo_dice(): void
    {
        $this->entrandoComo();

        Livewire::test(Marcar::class)
            ->call('rostroReconocido', '55555555', 0.2)
            ->assertHasErrors('cedula');
    }

    #[Test]
    public function sin_rostros_indexados_la_puerta_no_ofrece_buscar_por_la_cara(): void
    {
        // Sin índice no hay con qué comparar, y un botón que no puede funcionar estorba.
        $this->entrandoComo();

        Livewire::test(Marcar::class)->assertDontSee('Buscar por la cara');
    }

    #[Test]
    public function con_rostros_indexados_si_lo_ofrece(): void
    {
        $this->entrandoComo();
        app(Rostros::class)->guardar($this->trabajador(), $this->descriptor());

        Livewire::test(Marcar::class)->assertSee('Buscar por la cara');
    }
}
