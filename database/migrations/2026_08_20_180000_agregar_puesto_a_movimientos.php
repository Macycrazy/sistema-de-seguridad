<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La entrada de un vehículo puede quedar asignada a un puesto (plaza numerada). Es opcional —no
 * frena la puerta— pero cuando se pone, se sabe qué plaza ocupa ese vehículo, cuáles están tomadas
 * y cuál usa el que pernocta.
 *
 * «nullOnDelete»: si se quita el puesto del catálogo, el asiento no se cae; solo pierde la plaza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->foreignId('puesto_id')->nullable()->after('placa')->constrained('puestos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('puesto_id');
        });
    }
};
