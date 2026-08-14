<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los vehículos de una persona. Puede tener más de uno.
 *
 * Hasta ahora el vehículo vivía en cinco columnas de «personas», y eso daba por sentado que cada
 * quien tiene uno solo. No es cierto: hay quien viene en carro unos días y en moto otros. Con las
 * columnas en la ficha no había forma de guardar los dos, y elegir uno borraba el otro.
 *
 * Ahora cada vehículo es una fila, y en la puerta se marca CUÁL trae ese día.
 *
 * «movimientos» no cambia: sigue guardando su copia congelada —tipo, marca, modelo, color y
 * placa— y no un enlace a esta tabla. Es a propósito. Un enlace diría lo que el vehículo es HOY;
 * el asiento tiene que decir lo que era el día que se registró, aunque después se corrija la
 * ficha o se borre el vehículo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $tabla) {
            $tabla->id();

            // Si se borra la persona se van sus vehículos: no le sirven a nadie más. El
            // histórico no se toca, porque los movimientos llevan su propia copia.
            $tabla->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();

            // «carro» o «moto». Los valores los fija App\Services\DatosVehiculo.
            $tabla->string('tipo', 10);

            $tabla->string('marca', 40)->nullable();
            $tabla->string('modelo', 40)->nullable();
            $tabla->string('color', 30)->nullable();

            // La placa es lo que identifica al vehículo, así que aquí sí es obligatoria.
            // Guardada normalizada: solo letras y dígitos, en mayúsculas.
            $tabla->string('placa', 15);

            $tabla->timestamps();

            // La misma persona no puede tener dos veces la misma placa. Dos personas sí pueden
            // compartirla: un carro familiar lo trae hoy uno y mañana otro.
            $tabla->unique(['persona_id', 'placa']);

            // Para la pregunta de la puerta: «¿de quién es este carro?».
            $tabla->index('placa');
        });

        // Lo que ya estaba anotado en las fichas se muda aquí, sin perder nada.
        foreach (DB::table('personas')->whereNotNull('placa')->get() as $persona) {
            DB::table('vehiculos')->insert([
                'persona_id' => $persona->id,
                'tipo' => $persona->tipo_vehiculo ?: 'carro',
                'marca' => $persona->marca,
                'modelo' => $persona->modelo,
                'color' => $persona->color,
                'placa' => $persona->placa,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Y se van de «personas»: tener el dato en dos sitios acaba siempre con los dos
        // diciendo cosas distintas.
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->dropIndex(['placa']);
            $tabla->dropColumn(['tipo_vehiculo', 'marca', 'modelo', 'color', 'placa']);
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $tabla) {
            $tabla->string('tipo_vehiculo', 10)->nullable()->after('motivo');
            $tabla->string('marca', 40)->nullable()->after('tipo_vehiculo');
            $tabla->string('modelo', 40)->nullable()->after('marca');
            $tabla->string('color', 30)->nullable()->after('modelo');
            $tabla->string('placa', 15)->nullable()->after('color');
            $tabla->index('placa');
        });

        // Solo cabe uno por ficha: se devuelve el más reciente y se avisa de lo que se pierde.
        foreach (DB::table('personas')->pluck('id') as $personaId) {
            $vehiculo = DB::table('vehiculos')
                ->where('persona_id', $personaId)
                ->orderByDesc('id')
                ->first();

            if ($vehiculo) {
                DB::table('personas')->where('id', $personaId)->update([
                    'tipo_vehiculo' => $vehiculo->tipo,
                    'marca' => $vehiculo->marca,
                    'modelo' => $vehiculo->modelo,
                    'color' => $vehiculo->color,
                    'placa' => $vehiculo->placa,
                ]);
            }
        }

        Schema::dropIfExists('vehiculos');
    }
};
