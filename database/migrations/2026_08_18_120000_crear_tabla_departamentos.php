<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El organigrama como dato: la estructura de unidades del CIIP, no un texto suelto.
 *
 * Hasta ahora «dependencia» era una columna de texto libre en personas. Sirve para escribir, pero
 * no para agrupar: «GERENCIA DE LITIGIOS» y «Gerencia de Litigios» son dos cosas para la máquina,
 * y no hay forma de decir que una coordinación cuelga de una gerencia.
 *
 * Esta tabla lo vuelve estructura: cada unidad es un registro, con su nivel y su unidad madre
 * (parent_id). Es ADITIVO a propósito —no se toca ni se borra «dependencia» ni «ente» de
 * personas—, para que las partes 1, 2 y 3 sigan funcionando igual mientras se adopta. Personas
 * gana una FK opcional «departamento_id»; quien no la tenga sigue mostrándose por su texto.
 *
 * El nivel se infiere del nombre, que en el CIIP ya trae la jerarquía escrita:
 *   PRESIDENCIA (0) · GERENCIA GENERAL … (1) · GERENCIA … (2) · COORDINACIÓN … (3).
 * La unidad madre NO se adivina —quién cuelga de quién es cosa de quien conoce la casa—: el
 * backfill deja todo plano y el administrador arma el árbol desde la pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 150);
            // Código corto opcional, por si la casa maneja siglas (GGA, CJ…). No obligatorio.
            $tabla->string('codigo', 20)->nullable();
            // A qué ente pertenece, cuando se sabe. Nulo si es mixto o aún sin asignar.
            $tabla->string('ente', 20)->nullable()->index();
            // Pista de profundidad: 0 presidencia … 3 coordinación. Ordena y sangra la lista.
            $tabla->unsignedTinyInteger('nivel')->default(2);
            // La unidad madre. Nula en la raíz. Si se borra la madre, sus hijas quedan sueltas.
            $tabla->foreignId('parent_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index('parent_id');
        });

        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->foreignId('departamento_id')->nullable()->after('dependencia')
                ->constrained('departamentos')->nullOnDelete();
        });

        $this->sembrarDesdeDependencias();
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('departamento_id');
        });

        Schema::dropIfExists('departamentos');
    }

    /**
     * Crea un departamento por cada «dependencia» distinta que ya haya, y enlaza a su gente.
     *
     * Fiel a lo que hay: no dedup­lica por mayúsculas ni inventa madres. Si dos filas escribieron
     * la misma unidad distinto, quedan dos departamentos que el administrador podrá fundir a mano
     * —mejor eso que fusionar por adivinanza dos unidades que quizá no lo eran—.
     */
    private function sembrarDesdeDependencias(): void
    {
        $dependencias = DB::table('personas')
            ->select('dependencia')
            ->selectRaw('max(ente) as ente_muestra')
            ->selectRaw('count(distinct ente) as entes')
            ->whereNotNull('dependencia')
            ->where('dependencia', '<>', '')
            ->groupBy('dependencia')
            ->get();

        foreach ($dependencias as $fila) {
            $id = DB::table('departamentos')->insertGetId([
                'nombre' => $fila->dependencia,
                // El ente solo se fija si toda la gente de esa unidad coincide en uno; si hay
                // mezcla (entes > 1), se deja nulo para no mentir.
                'ente' => $fila->entes === 1 ? $fila->ente_muestra : null,
                'nivel' => $this->nivelDe($fila->dependencia),
                'parent_id' => null,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('personas')
                ->where('dependencia', $fila->dependencia)
                ->update(['departamento_id' => $id]);
        }
    }

    /** El nivel según cómo el CIIP nombra sus unidades. Ante la duda, nivel medio. */
    private function nivelDe(string $nombre): int
    {
        $n = mb_strtoupper($nombre);

        return match (true) {
            str_starts_with($n, 'PRESIDENCIA') => 0,
            str_contains($n, 'GERENCIA GENERAL') => 1,
            str_starts_with($n, 'GERENCIA') => 2,
            str_starts_with($n, 'COORDINACIÓN') || str_starts_with($n, 'COORDINACION') => 3,
            default => 2,
        };
    }
};
