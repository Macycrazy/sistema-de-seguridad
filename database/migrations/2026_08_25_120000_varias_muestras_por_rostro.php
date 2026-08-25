<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varias muestras de la cara de una misma persona, no una sola.
 *
 * Con una sola —la del carnet— el reconocimiento depende de que esa foto se parezca a cómo viene
 * la persona hoy, y las fotos de carnet son de hace años: otro peinado, gafas nuevas, barba, otra
 * luz. Cada muestra más es otra oportunidad de reconocerla.
 *
 * Al comparar se toma la MEJOR de sus muestras, no el promedio: promediar una cara de 2019 con la
 * de hoy da una cara que no existe.
 *
 * Se quita el «unique» de persona_id y queda un índice normal, que es lo que hace falta para
 * traerse las muestras de alguien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rostros', function (Blueprint $tabla) {
            $tabla->dropUnique(['persona_id']);
            $tabla->index('persona_id');
        });
    }

    public function down(): void
    {
        // Se deja una sola por persona —la más reciente— antes de poder volver al «unique».
        $sobrantes = DB::table('rostros')
            ->select('persona_id', DB::raw('max(id) as ultima'))
            ->groupBy('persona_id')
            ->pluck('ultima');

        DB::table('rostros')->whereNotIn('id', $sobrantes)->delete();

        Schema::table('rostros', function (Blueprint $tabla) {
            $tabla->dropIndex(['persona_id']);
            $tabla->unique('persona_id');
        });
    }
};
