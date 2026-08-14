<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El vehículo en el que llega el invitado: marca, modelo, color y placa.
 *
 * Va en las DOS tablas, por la misma razón que el motivo (ver docs/esquema.md):
 *
 *   personas    -> el último vehículo conocido. Así, al invitado que vuelve en el mismo carro
 *                  ya le salen los datos escritos y solo hay que confirmarlos.
 *   movimientos -> una copia congelada del vehículo de ESE día. Si el lunes vino en su carro y
 *                  el jueves a pie, el asiento del lunes tiene que seguir diciendo el carro.
 *
 * Las cuatro columnas son NULAS a propósito: mucha gente entra caminando, y obligar a inventar
 * un vehículo llenaría la base de basura. Un invitado sin carro las deja las cuatro en nulo.
 *
 * La placa se guarda normalizada —solo letras y dígitos, en mayúsculas— igual que la cédula,
 * para que «AB123CD», «ab-123-cd» y «AB 123 CD» sean la misma placa al buscarla. Lo hace
 * App\Services\Vehiculo::normalizarPlaca().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->string('marca', 40)->nullable()->after('motivo');
            $tabla->string('modelo', 40)->nullable()->after('marca');
            $tabla->string('color', 30)->nullable()->after('modelo');
            $tabla->string('placa', 15)->nullable()->after('color');

            // Para la pregunta que se hace en la puerta: «¿de quién es este carro?».
            $tabla->index('placa');
        });

        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->string('marca', 40)->nullable()->after('motivo');
            $tabla->string('modelo', 40)->nullable()->after('marca');
            $tabla->string('color', 30)->nullable()->after('modelo');
            $tabla->string('placa', 15)->nullable()->after('color');

            // La misma búsqueda, pero sobre el histórico: «¿cuándo entró ese carro?».
            $tabla->index('placa');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->dropIndex(['placa']);
            $tabla->dropColumn(['marca', 'modelo', 'color', 'placa']);
        });

        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->dropIndex(['placa']);
            $tabla->dropColumn(['marca', 'modelo', 'color', 'placa']);
        });
    }
};
