<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Si un movimiento se registró desde el panel para corregir un olvido, y no en la puerta.
 *
 * Pasa todos los días: alguien entra, se va sin marcar la salida, y su entrada se queda abierta
 * para siempre. Esa persona sigue contando como «dentro» —el contador miente, y en una emergencia
 * la lista de quién está en el edificio trae gente que se fue anteayer— y su alerta no se va nunca.
 *
 * La regla del registro es que los movimientos NO se editan ni se borran: un error se corrige con
 * un movimiento nuevo. Así que la salida que faltó se registra de verdad, pero marcada, para que
 * nadie la confunda con alguien que pasó por la puerta. El histórico sigue contando lo que ocurrió:
 * que entró, que nadie le marcó la salida, y que alguien lo cerró después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->boolean('es_correccion')->default(false)->after('a_pie');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->dropColumn('es_correccion');
        });
    }
};
