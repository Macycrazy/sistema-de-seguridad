<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién MARCÓ la salida de un vehículo, además de quién se lo llevó.
 *
 * No es lo mismo y hacían falta los dos: «conductor de salida» es a quién se le entregó el carro
 * —un nombre que teclea el guardia—, y esto es qué usuario del sistema lo dio por entregado. Al
 * anotar la entrada ya se guardaba («usuario_id»); al sacarlo, no se guardaba nada. Si mañana sale
 * un vehículo que no debía, con esto se sabe desde qué cuenta se hizo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos_fijos', function (Blueprint $tabla) {
            $tabla->foreignId('salida_usuario_id')->nullable()->after('salida_conductor_nombre')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos_fijos', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('salida_usuario_id');
        });
    }
};
