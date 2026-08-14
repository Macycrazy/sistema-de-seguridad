<?php

use App\Usuarios\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qué puede hacer cada rol.
 *
 * Una fila es una concesión: «el rol X tiene el permiso Y». Lo que no está, no se puede. Así el
 * administrador cambia los permisos desde la pantalla de roles en vez de que haga falta tocar
 * código y volver a desplegar.
 *
 * Lo que NO se guarda aquí es el orden de los roles —quién puede tocar a quién—, que sigue siendo
 * código en Rol::alcanza(). Si eso fuera configurable, quien entrara a la pantalla de permisos se
 * ascendería solo.
 *
 * La migración siembra los valores por omisión, que son la tabla del README. No es un seeder de
 * desarrollo: sin estas filas el sistema arranca sin que nadie pueda hacer nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos_de_rol', function (Blueprint $table) {
            $table->id();
            $table->string('rol', 20)->index();
            $table->string('permiso', 40);
            $table->timestamps();

            // Una concesión no se repite: o está o no está.
            $table->unique(['rol', 'permiso']);
        });

        $ahora = now();
        $filas = [];

        foreach (Permiso::cases() as $permiso) {
            foreach ($permiso->porOmision() as $rol) {
                $filas[] = [
                    'rol' => $rol->value,
                    'permiso' => $permiso->value,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        DB::table('permisos_de_rol')->insert($filas);
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_de_rol');
    }
};
