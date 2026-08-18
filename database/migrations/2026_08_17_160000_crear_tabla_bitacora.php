<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La bitácora de auditoría: quién hizo qué y cuándo.
 *
 * El permiso «ver-auditoria» existía desde la parte 3, pero sin nada detrás. Esto es lo que había
 * detrás: un rastro de las acciones que no dejan otra huella.
 *
 * A propósito NO anota los marcajes de la puerta: cada movimiento ya guarda su «usuario_id», así
 * que quién marcó a quién ya consta. La bitácora cubre lo que hoy no dejaba rastro —quién consultó
 * el histórico de una persona, quién miró una foto, quién exportó, y todos los cambios de
 * administración (usuarios, permisos, reglas, oficinas, personal).
 *
 * Es inmutable, como los movimientos: no lleva «updated_at» y una entrada no se edita ni se borra.
 * Un registro de auditoría que se pudiera alterar no probaría nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $tabla) {
            $tabla->id();

            // Quién. Nulo solo desde la consola (un comando, un seeder): ahí no hay sesión.
            $tabla->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            // Qué hizo, de un vocabulario cerrado. Ver App\Services\Auditoria\Auditoria.
            $tabla->string('accion', 40)->index();

            // Sobre qué (una cédula, un nombre de usuario, «reglas de tiempo»), para poder buscar.
            $tabla->string('sobre', 120)->nullable();

            // Un detalle en texto, cuando ayuda («de vigilante a supervisor»).
            $tabla->string('detalle', 255)->nullable();

            $tabla->ipAddress('ip')->nullable();

            // La hora del hecho. Indexada porque la pantalla lista siempre por fecha.
            $tabla->timestamp('ocurrio_en')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
