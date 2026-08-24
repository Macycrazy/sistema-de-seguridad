<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El rostro de una persona, guardado como números y no como imagen.
 *
 * Un «descriptor» son 128 decimales que resumen una cara: dos fotos de la misma persona dan
 * descriptores parecidos, y de personas distintas, lejanos. Con eso se compara. NO se guarda
 * ninguna foto aquí —la foto sigue viviendo en el sistema de carnets— y de un descriptor no se
 * puede reconstruir la cara.
 *
 * Aun así identifica a una persona igual de bien que una foto, así que es un dato personal: se
 * borra con la persona (cascade) y la pantalla puede vaciar el índice entero.
 *
 * Una persona, un rostro: si se reindexa, se pisa el que había.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rostros', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('persona_id')->unique()->constrained('personas')->cascadeOnDelete();

            // Los 128 decimales, como lista JSON. En texto y no en binario a propósito: cabe de
            // sobra, se lee al depurar, y las dos bases lo entienden igual.
            $tabla->json('descriptor');

            // De dónde salió la cara que se indexó, para saber qué reindexar cuando cambie.
            $tabla->string('origen', 20)->default('carnet');

            $tabla->timestamp('calculado_en');
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rostros');
    }
};
