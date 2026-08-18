<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las visitas que se esperan: una agenda de quién va a venir, antes de que venga.
 *
 * Es ADITIVA y no toca el marcaje de la puerta (parte 1): allí se sigue marcando la entrada y la
 * salida real de cada quien. Esto es la recepción anticipándose —«mañana viene el ingeniero Pérez
 * a ver a la gerente de tecnología»— para que el vigilante, al llegar esa persona, ya sepa que se
 * la esperaba y a quién viene a ver, en vez de averiguarlo en el momento.
 *
 * No es una persona del padrón: un visitante esperado puede no tener ficha, o traer pasaporte. Por
 * eso guarda su cédula y su nombre sueltos, no una FK a personas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas_esperadas', function (Blueprint $tabla) {
            $tabla->id();

            // La cédula puede faltar (a veces solo se sabe el nombre de quien viene). Sin puntos.
            $tabla->string('cedula', 20)->nullable()->index();
            $tabla->string('nombre', 120);

            // A quién viene a ver, y para qué. Texto libre: el anfitrión puede no estar en el padrón.
            $tabla->string('a_quien_visita', 120)->nullable();
            $tabla->string('motivo', 150)->nullable();

            // El día en que se la espera. Es la columna por la que se consulta «lo de hoy».
            $tabla->date('fecha_esperada')->index();

            // esperada -> llego | cancelada. «Vencida» no se guarda: es esperada + fecha pasada.
            $tabla->string('estado', 20)->default('esperada')->index();

            $tabla->string('notas', 255)->nullable();

            // Quién la agendó. Nulo si esa cuenta se borró después.
            $tabla->foreignId('registrada_por')->nullable()->constrained('users')->nullOnDelete();

            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas_esperadas');
    }
};
