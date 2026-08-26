<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos que alguien ya miró y decidió que no hacen falta por ahora.
 *
 * Hay permanencias largas que son de verdad: el guardia de noche, quien se queda con una avería,
 * un turno de veinte horas. A esos no se les puede marcar una salida que no ocurrió —sería mentir
 * en el registro— pero su aviso tampoco puede quedarse encendido para siempre, porque una pantalla
 * con diez avisos viejos deja de mirarse y el día que salte uno de verdad se pierde entre ellos.
 *
 * Se silencia HASTA una hora, no para siempre: si mañana esa persona sigue dentro, el aviso vuelve.
 * Y queda quién lo silenció, porque es una decisión, no un descarte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_silenciadas', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->string('tipo', 30);
            $tabla->foreignId('persona_id')->nullable()->constrained('personas')->cascadeOnDelete();

            $tabla->timestamp('hasta');
            $tabla->string('motivo', 120)->nullable();
            $tabla->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            $tabla->timestamps();

            $tabla->index(['tipo', 'persona_id', 'hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_silenciadas');
    }
};
