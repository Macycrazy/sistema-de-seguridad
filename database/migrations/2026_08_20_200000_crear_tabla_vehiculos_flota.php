<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La flota de la empresa: los vehículos propios, cargados una vez para elegirlos al anotarlos en un
 * puesto, sin teclear la placa cada vez. Entran y salen las veces que haga falta —cada estadía es
 * un registro en «vehiculos_fijos»—; esto es solo el catálogo, no la ocupación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos_flota', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('placa', 15)->unique();
            $tabla->string('tipo_vehiculo', 10);
            $tabla->string('marca', 40)->nullable();
            $tabla->string('color', 30)->nullable();
            $tabla->string('nota', 120)->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos_flota');
    }
};
