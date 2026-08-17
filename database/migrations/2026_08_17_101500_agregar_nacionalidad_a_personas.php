<?php

use App\Models\Persona;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La letra de la cédula: V (venezolano), E (extranjero) o J (jurídico).
 *
 * Hasta ahora la cédula se guardaba en solo dígitos y la letra se tiraba. Eso hacía que
 * «V-12345678» y «E-12345678» fueran LA MISMA PERSONA para el sistema: al segundo que llegara le
 * saldría la ficha del primero —su nombre, su foto, su dependencia— y se le marcaría la entrada a
 * otro. En un sistema que existe para probar quién estuvo dónde, eso no se sostiene.
 *
 * Por eso el número deja de ser único por su cuenta: lo único es la pareja (nacionalidad, cedula).
 *
 * Las fichas que ya estaban quedan en «V», que es lo que se venía dando por sentado al no
 * preguntarlo. Si alguna era de un extranjero, se corrige a mano desde la pantalla de personas —no
 * hay forma de adivinarlo desde aquí—.
 *
 * OJO al orden: el índice único viejo se suelta ANTES de crear el nuevo. SQLite —donde corren las
 * pruebas— no deja soltar una columna ni cambiar un índice en la misma llamada que lo usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            // Una sola letra. Por omisión «V»: es lo que había implícito en todas las fichas
            // anteriores, que se registraron sin preguntar.
            $table->char('nacionalidad', 1)
                ->default(Persona::VENEZOLANO)
                ->after('cedula');
        });

        Schema::table('personas', function (Blueprint $table) {
            $table->dropUnique('personas_cedula_unique');
        });

        Schema::table('personas', function (Blueprint $table) {
            // El número solo ya no basta: lo que no se puede repetir es la cédula entera.
            $table->unique(['nacionalidad', 'cedula']);

            // Se sigue buscando por número —el vigilante teclea los dígitos y la letra viene del
            // desplegable—, así que el número necesita su propio índice para no quedarse sin él.
            $table->index('cedula');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropUnique(['nacionalidad', 'cedula']);
            $table->dropIndex(['cedula']);
        });

        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn('nacionalidad');
        });

        Schema::table('personas', function (Blueprint $table) {
            $table->unique('cedula');
        });
    }
};
