<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los roles como dato, para poder añadir más desde la pantalla.
 *
 * Los tres base (vigilante, supervisor, administrador) se siembran aquí marcados como «base»: no se
 * borran ni se les cambia el nivel. El administrador puede agregar roles nuevos, cada uno anclado a
 * un nivel (1, 2 o 3), que es lo que decide a quién puede tocar.
 *
 * La columna «rol» de «users» y de «permisos_de_rol» guarda el slug; no se pone clave foránea a
 * propósito, igual que hasta ahora, para no atarse las manos al renombrar o migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('slug', 40)->unique();
            $tabla->string('nombre', 60);
            // 1 = vigilante, 2 = supervisor, 3 = administrador. Decide la jerarquía.
            $tabla->unsignedTinyInteger('nivel');
            // Los tres fijos. No se borran ni se les cambia el nivel desde la pantalla.
            $tabla->boolean('base')->default(false);
            $tabla->timestamps();
        });

        $ahora = now();

        DB::table('roles')->insert([
            ['slug' => 'vigilante', 'nombre' => 'Vigilante', 'nivel' => 1, 'base' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['slug' => 'supervisor', 'nombre' => 'Supervisor', 'nivel' => 2, 'base' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['slug' => 'administrador', 'nombre' => 'Administrador', 'nivel' => 3, 'base' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
