<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cada oficina/piso se le asocia su gerencia, para que al asignarle el piso a un trabajador se
 * ofrezcan los pisos de su gerencia. Es texto —el mismo que lleva «dependencia» del trabajador—,
 * no una FK: así casa con la nómina sin depender de que el organigrama esté enlazado.
 *
 * Aditivo y nullable: una oficina sin gerencia sigue valiendo (es un sitio del edificio igual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oficinas', function (Blueprint $tabla) {
            $tabla->string('gerencia', 120)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('oficinas', function (Blueprint $tabla) {
            $tabla->dropColumn('gerencia');
        });
    }
};
