<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una persona es quien puede pasar por la puerta: un trabajador o un invitado.
 *
 * Van en la misma tabla porque en la puerta se tratan igual: se teclea una cédula y se marca.
 * La cédula es única entre las dos: nadie es trabajador e invitado a la vez.
 *
 * Las columnas que solo aplican a un tipo van nulas en el otro:
 *   trabajador -> dependencia, foto_ruta      (vienen del sistema de carnets)
 *   invitado   -> visita                      (a quién viene a ver)
 *
 * Del invitado se guarda lo mínimo: nombre y a quién visita. Nada de foto del documento,
 * teléfono ni dirección.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $tabla) {
            $tabla->id();

            // Solo dígitos, sin puntos ni guiones: así «12.345.678» y «12345678» son la misma.
            $tabla->string('cedula', 20)->unique();

            // Los mismos valores que entiende <x-etiqueta tipo="..."> en la base visual.
            $tabla->string('tipo', 20)->index();

            $tabla->string('nombre', 120);
            $tabla->string('dependencia', 120)->nullable();
            $tabla->string('foto_ruta', 255)->nullable();
            $tabla->string('visita', 120)->nullable();

            // Un trabajador que ya no labora aquí no se borra: se desactiva y su histórico queda.
            $tabla->boolean('activo')->default(true);

            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
