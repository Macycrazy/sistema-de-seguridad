<?php

use App\Usuarios\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el permiso «gestionar-edificio» para las bases que ya se migraron.
 *
 * Igual que con los otros permisos: una base nueva lo trae de la migración de permisos_de_rol,
 * que lee el enum al correr; ésta es para las que se crearon antes de que existiera.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        foreach (Permiso::GESTIONAR_EDIFICIO->porOmision() as $rol) {
            DB::table('permisos_de_rol')->updateOrInsert(
                ['rol' => $rol->value, 'permiso' => Permiso::GESTIONAR_EDIFICIO->value],
                ['created_at' => $ahora, 'updated_at' => $ahora],
            );
        }
    }

    public function down(): void
    {
        DB::table('permisos_de_rol')
            ->where('permiso', Permiso::GESTIONAR_EDIFICIO->value)
            ->delete();
    }
};
