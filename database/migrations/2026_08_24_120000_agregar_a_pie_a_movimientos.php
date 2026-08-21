<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Si al marcar se dijo que la persona iba a pie.
 *
 * Hasta ahora «a pie» era la ausencia de vehículo, y ausencia de dato no es un dato: no se
 * distinguía entre «llegó caminando» y «nadie anotó nada». El registro enseñaba un guion en los
 * dos casos, y un guion no se lee como «vino a pie» —se lee como que falta algo—.
 *
 * Nulo a propósito, y no falso por omisión: los asientos de antes de esta columna no dicen nada de
 * cómo llegó esa persona, y ponerles «a pie» sería inventarlo para siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->boolean('a_pie')->nullable()->after('placa');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->dropColumn('a_pie');
        });
    }
};
