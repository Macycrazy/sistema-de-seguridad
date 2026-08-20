<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los puestos —plazas numeradas— del estacionamiento. Cada vehículo que entra se para en uno, y
 * así se sabe cuáles están tomados y cuáles libres, y qué plaza ocupa el que pernocta.
 *
 * El «tipo» dice si la plaza es de carro, de moto, o sirve para cualquiera (nulo). La «zona» es
 * opcional, para agrupar («Sótano 1», «Frente»). El catálogo lo administra el edificio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puestos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('codigo', 20)->unique();
            // 'carro', 'moto' o nulo (cualquiera). Es texto, como en movimientos.
            $tabla->string('tipo', 10)->nullable();
            $tabla->string('zona', 60)->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->unsignedInteger('orden')->default(0);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puestos');
    }
};
