<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El hash de la foto con la que se indexó ese rostro.
 *
 * El índice guarda la cara que TENÍA esa persona el día que se miró. Si en carnets le cambian la
 * foto, aquí nadie se entera: el reconocimiento sigue buscando la cara vieja y falla sin decir por
 * qué. Hasta ahora la única salida era volver a mirarlos a todos.
 *
 * Carnets publica el hash de cada foto en su padrón, así que guardando el que se usó se sabe A
 * QUIÉN hay que volver a mirar: a los que ya no coinciden.
 *
 * Nulo en los rostros de antes de esta columna: de ellos no se sabe con qué foto se hicieron, así
 * que se tratan como «no comprobables» y no como «desactualizados» —marcarlos todos para reindexar
 * sería el mismo trabajo que no tener la columna—.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rostros', function (Blueprint $tabla) {
            $tabla->string('hash_foto', 64)->nullable()->after('origen');
        });
    }

    public function down(): void
    {
        Schema::table('rostros', function (Blueprint $tabla) {
            $tabla->dropColumn('hash_foto');
        });
    }
};
