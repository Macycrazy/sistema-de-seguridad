<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un usuario del sistema podía repetir la cédula de otro sin problema: la cédula aquí es un dato
 * de contacto, no la identidad (eso es «usuario»). El único obligaba a inventar cédulas o borrar
 * la ajena para crear una cuenta, así que se quita. La columna sigue: solo deja de ser única.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_cedula_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('cedula');
        });
    }
};
