<?php

use App\Usuarios\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el permiso «gestionar-personal» para las bases que ya se migraron.
 *
 * La migración de permisos_de_rol siembra los valores por omisión leyendo el enum en el momento
 * de correr, así que una base nueva ya trae este permiso. Esta migración es para las que se
 * crearon antes de que el permiso existiera: les añade sus filas por omisión sin duplicar las que
 * ya estén.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        foreach (Permiso::GESTIONAR_PERSONAL->porOmision() as $rol) {
            DB::table('permisos_de_rol')->updateOrInsert(
                ['rol' => $rol->value, 'permiso' => Permiso::GESTIONAR_PERSONAL->value],
                ['created_at' => $ahora, 'updated_at' => $ahora],
            );
        }
    }

    public function down(): void
    {
        DB::table('permisos_de_rol')
            ->where('permiso', Permiso::GESTIONAR_PERSONAL->value)
            ->delete();
    }
};
