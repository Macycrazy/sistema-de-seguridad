<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La bitácora de vehículos fijos del estacionamiento: los de la empresa, o los que ya estaban y se
 * quedan, que ocupan un puesto sin pasar por el marcaje de una persona.
 *
 * Solo placa y puesto —sin dueño ni cédula—: se anota qué vehículo ocupa qué plaza y desde cuándo.
 * Ocupa el puesto hasta que se le marca la salida («salio_en»); mientras esté abierto, ese puesto
 * cuenta como tomado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos_fijos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('puesto_id')->constrained('puestos')->cascadeOnDelete();
            $tabla->string('placa', 15);
            $tabla->string('tipo_vehiculo', 10);
            $tabla->string('marca', 40)->nullable();
            $tabla->string('color', 30)->nullable();
            $tabla->string('nota', 120)->nullable();
            $tabla->timestamp('entro_en');
            $tabla->timestamp('salio_en')->nullable();
            $tabla->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamps();

            // Lo que se consulta siempre: los que siguen dentro (salio_en nulo).
            $tabla->index('salio_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos_fijos');
    }
};
