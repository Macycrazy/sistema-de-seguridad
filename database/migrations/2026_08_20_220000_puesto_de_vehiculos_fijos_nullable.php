<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un vehículo puede estar dentro SIN puesto todavía: entra, y quien está en el estacionamiento le
 * asigna la plaza cuando ve dónde quedó. Así que «puesto_id» pasa a ser nullable, y al borrar un
 * puesto la estadía no se cae —solo pierde la plaza— («nullOnDelete» en vez de «cascadeOnDelete»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos_fijos', function (Blueprint $tabla) {
            $tabla->dropForeign(['puesto_id']);
        });

        Schema::table('vehiculos_fijos', function (Blueprint $tabla) {
            $tabla->unsignedBigInteger('puesto_id')->nullable()->change();
            $tabla->foreign('puesto_id')->references('id')->on('puestos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos_fijos', function (Blueprint $tabla) {
            $tabla->dropForeign(['puesto_id']);
        });

        Schema::table('vehiculos_fijos', function (Blueprint $tabla) {
            $tabla->unsignedBigInteger('puesto_id')->nullable(false)->change();
            $tabla->foreign('puesto_id')->references('id')->on('puestos')->cascadeOnDelete();
        });
    }
};
