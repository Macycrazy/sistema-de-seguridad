<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un movimiento es una entrada o una salida: el asiento que deja el botón de la puerta.
 *
 * Regla del proyecto: los movimientos no se editan ni se borran. Un error se corrige con un
 * movimiento nuevo. Por eso esta tabla no lleva «updated_at»: no habría nada que actualizar,
 * y tener la columna invitaría a hacerlo.
 *
 * «ocurrio_en» es la única hora del movimiento, y es la que debe usar el registro (parte 2)
 * para sus listados y filtros por fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $tabla) {
            $tabla->id();

            // restrictOnDelete: si una persona tiene movimientos, no se puede borrar.
            // El histórico de la puerta pesa más que la comodidad de borrar una ficha.
            $tabla->foreignId('persona_id')->constrained('personas')->restrictOnDelete();

            // Los mismos valores que entiende <x-etiqueta tipo="...">: entrada · salida.
            $tabla->string('tipo', 20);

            $tabla->timestamp('ocurrio_en')->index();

            // Quién lo registró. Nulo por ahora porque el ingreso con usuario es la parte 3;
            // cuando esa parte esté lista, pasa a obligatorio.
            $tabla->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            // Copia del motivo que traía el invitado en ese momento. Se guarda aquí y no solo en
            // «personas» porque el asiento tiene que seguir diciendo la verdad de ese día,
            // aunque la próxima vez el invitado venga por otra cosa.
            //
            // Se llama «motivo» desde la migración 2026_08_12_211127_renombrar_visita_a_motivo;
            // aquí se deja el nombre viejo porque es esa la que lo cambia.
            $tabla->string('visita', 120)->nullable();

            // Para «quién está dentro»: se busca el último movimiento de cada persona.
            $tabla->index(['persona_id', 'ocurrio_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
