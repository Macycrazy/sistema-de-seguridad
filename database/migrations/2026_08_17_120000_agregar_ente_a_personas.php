<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El ente al que pertenece la persona.
 *
 * Tres organismos comparten el edificio —CIIP, Marca País y VENAPP— y, por tanto, el puesto de
 * vigilancia. El listado de personal los separa en su columna ENTE, y el reporte del día suele
 * pedirse por uno. Sin esta columna, el filtro por ente del registro no distinguía nada.
 *
 * Nula a propósito: un invitado no pertenece a ningún ente, y un trabajador puede venir de una
 * carga que todavía no lo traiga. Los valores son los del enum App\Services\Registro\Ente
 * ('ciip', 'marca-pais', 'venapp'), los mismos que entiende el filtro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->string('ente', 20)->nullable()->index()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->dropIndex(['ente']);
            $tabla->dropColumn('ente');
        });
    }
};
