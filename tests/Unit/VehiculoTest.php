<?php

namespace Tests\Unit;

use App\Services\Vehiculo;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * La limpieza de los cuatro datos del vehículo. No toca la base, así que no lleva
 * RefreshDatabase; sí hereda del TestCase de Laravel porque ValidationException necesita la
 * aplicación arrancada para armar el mensaje.
 */
class VehiculoTest extends TestCase
{
    public function test_la_placa_queda_en_mayusculas_y_sin_guiones_ni_espacios(): void
    {
        foreach (['AB123CD', 'ab-123-cd', 'AB 123 CD', ' ab123cd '] as $tecleada) {
            $this->assertSame(
                'AB123CD',
                Vehiculo::normalizarPlaca($tecleada),
                "Falló tecleando «{$tecleada}»",
            );
        }
    }

    public function test_una_placa_vacia_es_nula_y_no_una_cadena_vacia(): void
    {
        // En la base tiene que quedar NULL: es como se anota «no trajo carro».
        $this->assertNull(Vehiculo::normalizarPlaca(''));
        $this->assertNull(Vehiculo::normalizarPlaca('   '));
        $this->assertNull(Vehiculo::normalizarPlaca('- -'));
        $this->assertNull(Vehiculo::normalizarPlaca(null));
    }

    public function test_los_demas_datos_se_recortan_y_no_se_quedan_con_espacios_de_sobra(): void
    {
        $vehiculo = Vehiculo::desde('  Toyota ', 'Corolla   LE', ' Gris  ', 'AB123CD');

        $this->assertSame('Toyota', $vehiculo->marca);
        $this->assertSame('Corolla LE', $vehiculo->modelo);
        $this->assertSame('Gris', $vehiculo->color);
    }

    public function test_sin_ningun_dato_el_vehiculo_esta_vacio(): void
    {
        $this->assertTrue(Vehiculo::desde()->vacio());
        $this->assertTrue(Vehiculo::desde('', '  ', '', null)->vacio());
        $this->assertFalse(Vehiculo::desde(placa: 'AB123CD')->vacio());
    }

    public function test_un_vehiculo_vacio_es_valido_porque_la_gente_entra_caminando(): void
    {
        Vehiculo::desde()->exigirValido();

        $this->assertTrue(true, 'Un vehículo vacío no debe dar error.');
    }

    public function test_un_vehiculo_a_medias_sin_placa_no_es_valido(): void
    {
        $this->expectException(ValidationException::class);

        Vehiculo::desde(marca: 'Toyota', color: 'Gris')->exigirValido();
    }

    public function test_con_la_placa_basta_aunque_no_se_sepa_la_marca(): void
    {
        // En la puerta se anota lo que se ve. Si solo se alcanzó a leer la placa, sirve igual.
        Vehiculo::desde(placa: 'AB123CD')->exigirValido();

        $this->assertTrue(true, 'Solo con la placa debe bastar.');
    }

    public function test_la_placa_no_se_pasa_del_largo_de_la_columna(): void
    {
        $this->assertSame(
            Vehiculo::LARGO_PLACA,
            mb_strlen(Vehiculo::normalizarPlaca(str_repeat('A', 40))),
        );
    }

    public function test_la_descripcion_se_lee_de_un_vistazo(): void
    {
        $this->assertSame(
            'Toyota Corolla · Gris · AB123CD',
            Vehiculo::desde('Toyota', 'Corolla', 'Gris', 'AB123CD')->descripcion(),
        );

        // Y no deja separadores sueltos cuando falta algún dato.
        $this->assertSame('AB123CD', Vehiculo::desde(placa: 'AB123CD')->descripcion());
        $this->assertSame('', Vehiculo::desde()->descripcion());
    }
}
