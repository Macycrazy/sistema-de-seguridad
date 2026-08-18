<?php

use App\Services\Marcaje;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los parámetros que el administrador ajusta sin tocar código.
 *
 * Empiezan con las reglas de tiempo del marcaje —cuánto se espera entre una entrada y otra, entre
 * la entrada y su salida, y el plazo antiduplicado—, que estaban como constantes en Marcaje. Al
 * ser el edificio y el turno cosas que Seguridad afina, tienen que poder cambiarse desde una
 * pantalla y valer al instante, sin volver a desplegar.
 *
 * Cada parámetro es una pareja clave/valor. La UNIDAD va en el nombre de la clave —«segundos_…»,
 * «minutos_…»— para que valor sea siempre un entero pelado. Los valores por omisión son los que
 * tenían las constantes, así que nada cambia de comportamiento al migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('clave', 40)->unique();
            $tabla->integer('valor');
            $tabla->timestamps();
        });

        $ahora = now();
        $defaults = [
            'segundos_antiduplicado' => Marcaje::SEGUNDOS_ANTIDUPLICADO,
            'minutos_entre_entradas' => Marcaje::MINUTOS_ENTRE_ENTRADAS,
            'minutos_entre_entrada_y_salida' => Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA,
            'minutos_entre_salida_y_entrada' => Marcaje::MINUTOS_ENTRE_SALIDA_Y_ENTRADA,
        ];

        foreach ($defaults as $clave => $valor) {
            DB::table('parametros')->insert([
                'clave' => $clave,
                'valor' => $valor,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};
