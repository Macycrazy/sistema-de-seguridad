<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de oficinas del edificio, ahora en la base.
 *
 * Estaba en config/edificio.php. Se mueve aquí para que un administrador lo gestione desde una
 * pantalla —alguien se muda, se desocupa un piso, se renombra un área— sin editar un archivo y
 * volver a desplegar. La config sigue existiendo como valores de fábrica: esta migración los
 * siembra, y de ahí en adelante manda la base.
 *
 *   · «codigo» es el que ya se usa en las fichas: «2-1» es piso 2, oficina 1. Los que no llevan
 *     guion —«7», «LOBBY»— son un sitio entero.
 *   · «nombre» es opcional y de respaldo: el nombre real de una oficina sale de las fichas del
 *     personal que labora en ella; esto solo cubre las que no tienen a nadie asignado.
 *   · «orden» conserva el orden en que se ofrecen, que en config era el del arreglo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oficinas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('codigo', 20)->unique();
            $tabla->string('nombre', 60)->nullable();
            $tabla->integer('orden')->default(0);
            $tabla->timestamps();
        });

        // Siembra desde config/edificio.php: nada se pierde al mover el catálogo a la base.
        $nombres = (array) config('edificio.nombres', []);
        $ahora = now();
        $filas = [];

        foreach (array_values((array) config('edificio.oficinas', [])) as $orden => $codigo) {
            $filas[] = [
                'codigo' => $codigo,
                'nombre' => $nombres[$codigo] ?? null,
                'orden' => $orden,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        if ($filas !== []) {
            DB::table('oficinas')->insert($filas);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oficinas');
    }
};
