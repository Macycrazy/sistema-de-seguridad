<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El rastro: quién hizo qué y cuándo.
 *
 * Es la tabla con la que el README da la parte 3 por terminada — «se puede responder, mirando el
 * sistema, quién consultó los datos de una persona y en qué momento».
 *
 * Como «movimientos», estos asientos NO se editan ni se borran, y por eso tampoco llevan
 * «updated_at»: tenerlo sería mentir, y además invitaría a actualizarlos. Un rastro que se puede
 * corregir no prueba nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();

            // Nulo solo en el ingreso fallido: ahí todavía no hay usuario que anotar.
            // RESTRICT al borrar, aunque los usuarios no se borren: si algún día alguien lo
            // intenta, que se tope con esto en vez de dejar el rastro apuntando al vacío.
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->string('accion', 40)->index();

            // A quién le cayó, cuando aplica. Nulo en lo que no va contra una persona concreta.
            $table->foreignId('persona_id')->nullable()->constrained('personas')->restrictOnDelete();

            // La cédula consultada, el filtro exportado, el usuario creado. Texto corto y legible:
            // esto se lee en una pantalla, no se procesa.
            $table->string('detalle', 255)->nullable();

            $table->string('ip', 45)->nullable();

            // La hora del hecho, no la de la fila. Es la que se filtra y por la que se ordena.
            $table->timestamp('ocurrio_en')->index();

            // Sin updated_at a propósito. Ver el comentario de arriba.
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('auditorias', function (Blueprint $table) {
            // La pregunta que de verdad se le hace a esta tabla: «quién consultó a esta persona,
            // y cuándo».
            $table->index(['persona_id', 'ocurrio_en']);

            // Y la otra: «qué hizo este usuario el martes».
            $table->index(['usuario_id', 'ocurrio_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
