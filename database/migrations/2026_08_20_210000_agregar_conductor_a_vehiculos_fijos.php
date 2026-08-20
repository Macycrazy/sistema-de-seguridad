<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El vehículo se anota con quién lo CONDUCE al entrar, y al sacarlo se anota quién se lo lleva —que
 * puede ser otro—. El conductor es una persona del sistema (por cédula) o un nombre libre. Además
 * se enlaza, si viene de la flota, al vehículo de la empresa del catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos_fijos', function (Blueprint $tabla) {
            $tabla->foreignId('flota_id')->nullable()->after('id')->constrained('vehiculos_flota')->nullOnDelete();

            // Conductor de entrada: persona del sistema, o un nombre suelto.
            $tabla->foreignId('conductor_id')->nullable()->after('color')->constrained('personas')->nullOnDelete();
            $tabla->string('conductor_nombre', 120)->nullable()->after('conductor_id');

            // Conductor de salida: quién se lo llevó (puede ser otro). Se llena al sacarlo.
            $tabla->foreignId('salida_conductor_id')->nullable()->after('salio_en')->constrained('personas')->nullOnDelete();
            $tabla->string('salida_conductor_nombre', 120)->nullable()->after('salida_conductor_id');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos_fijos', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('flota_id');
            $tabla->dropConstrainedForeignId('conductor_id');
            $tabla->dropConstrainedForeignId('salida_conductor_id');
            $tabla->dropColumn(['conductor_nombre', 'salida_conductor_nombre']);
        });
    }
};
