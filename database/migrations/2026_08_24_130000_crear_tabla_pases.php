<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de pases de visitante: las credenciales numeradas que Seguridad presta en la puerta.
 *
 * Se cargan una vez, como las plazas del estacionamiento, y de ahí sale cuáles están libres y
 * cuáles en manos de alguien. Un pase no se borra cuando se presta: se presta y se devuelve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pases', function (Blueprint $tabla) {
            $tabla->id();

            // Lo que va escrito en el pase: «V-01», «PASE 14». Único, porque es lo que se dice en
            // voz alta al entregarlo y lo que se busca cuando no aparece.
            $tabla->string('codigo', 20)->unique();

            // Para distinguir tandas o colores: «amarillos», «piso 3». Opcional.
            $tabla->string('nota', 120)->nullable();

            // Un pase que se perdió o se estropeó se deshabilita en vez de borrarse: las entregas
            // viejas siguen apuntando a él y el histórico tiene que poder leerse.
            $tabla->boolean('activo')->default(true);

            $tabla->integer('orden')->default(0);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pases');
    }
};
