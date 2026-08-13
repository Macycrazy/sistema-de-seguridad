<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De «visita» a «motivo».
 *
 * El dato que se le pide al invitado no es a quién viene a ver, sino **el motivo de la visita**:
 * «videoconferencia», «consultor», «entrega de material». Al usar la pantalla quedó claro que lo
 * que se anota es un motivo, no un nombre de persona.
 *
 * Se renombra en vez de dejar el nombre viejo porque una columna llamada «visita» que guarda un
 * motivo engañaría a quien lea la tabla — y las partes 2 y 3 la van a leer.
 *
 * Va como migración aparte, y no editando la que creó las tablas, para no obligar a nadie a
 * borrar su base local y perder lo que tenga registrado probando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->renameColumn('visita', 'motivo');
        });

        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->renameColumn('visita', 'motivo');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->renameColumn('motivo', 'visita');
        });

        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->renameColumn('motivo', 'visita');
        });
    }
};
