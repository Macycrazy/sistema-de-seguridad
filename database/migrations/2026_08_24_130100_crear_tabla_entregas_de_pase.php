<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada vez que un pase se entrega a alguien y vuelve: el préstamo, no el pase.
 *
 * Misma forma que las estadías del estacionamiento, porque es el mismo problema: un objeto
 * numerado que se presta y se devuelve. Mientras no tenga «devuelto_en», ese pase está en la calle
 * y el sistema puede decir en manos de quién.
 *
 * No se borra ni se edita: un pase que no vuelve tiene que seguir constando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas_de_pase', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('pase_id')->constrained('pases')->cascadeOnDelete();
            $tabla->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();

            $tabla->timestamp('entregado_en');
            $tabla->timestamp('devuelto_en')->nullable();

            // Quién lo entregó y quién lo recibió de vuelta: pueden ser dos guardias distintos,
            // igual que pasa con los vehículos.
            $tabla->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->foreignId('devuelto_usuario_id')->nullable()->constrained('users')->nullOnDelete();

            $tabla->timestamps();

            $tabla->index(['pase_id', 'devuelto_en']);
            $tabla->index(['persona_id', 'devuelto_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas_de_pase');
    }
};
