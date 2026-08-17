<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El piso, con el código que se usa en el edificio: «2-1», «2-2» y así.
 *
 * Significa dos cosas parecidas pero no iguales, y por eso va en la misma columna:
 *
 *   trabajador -> DÓNDE LABORA. Es fijo, viene con su ficha y no se le pregunta en la puerta.
 *   invitado   -> A DÓNDE SE DIRIGE hoy. Se le pregunta SIEMPRE, porque puede cambiar de una
 *                 visita a otra, igual que el motivo.
 *
 * En «movimientos» va la copia congelada del piso al que fue ESE día. Misma razón que el motivo
 * y el vehículo: el asiento tiene que seguir diciendo la verdad de su día aunque la ficha cambie.
 *
 * Diez caracteres bastan de sobra para «2-1». Se guarda normalizado —sin espacios y en
 * mayúsculas— por App\Models\Persona::normalizarPiso(), para que «2-1» y «2 - 1» no acaben
 * siendo dos pisos distintos al buscar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->string('piso', 10)->nullable()->after('dependencia');
        });

        Schema::table('movimientos', function (Blueprint $tabla) {
            // Para la parte 2: «¿quién subió al 2-1 hoy?» es una pregunta de la puerta.
            $tabla->string('piso', 10)->nullable()->after('motivo');
            $tabla->index('piso');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->dropColumn('piso');
        });

        Schema::table('movimientos', function (Blueprint $tabla) {
            $tabla->dropIndex(['piso']);
            $tabla->dropColumn('piso');
        });
    }
};
