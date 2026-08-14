<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Si el vehículo es un carro o una moto.
 *
 * Va aparte de la marca porque en la puerta son dos cosas distintas: no estacionan en el mismo
 * sitio, y «¿cuántas motos hay dentro?» es una pregunta que se hace. Guardarlo dentro de la marca
 * («Bera BR-150») obligaría a conocer la marca para saber qué es.
 *
 * Nula cuando no hay vehículo. Cuando lo hay, siempre tiene valor: si no se elige, se guarda
 * «carro», que es lo más común. Los valores los fija App\Services\Vehiculo.
 *
 * Con esta migración el vehículo deja de ser solo del invitado: el personal también estaciona
 * aquí, así que las columnas del vehículo valen para los dos tipos de persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->string('tipo_vehiculo', 10)->nullable()->after('motivo');
        });

        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->string('tipo_vehiculo', 10)->nullable()->after('motivo');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->dropColumn('tipo_vehiculo');
        });

        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->dropColumn('tipo_vehiculo');
        });
    }
};
