<?php

use App\Usuarios\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el permiso «gestionar-ajustes» para las bases que ya se migraron.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        foreach (Permiso::GESTIONAR_AJUSTES->porOmision() as $rol) {
            DB::table('permisos_de_rol')->updateOrInsert(
                ['rol' => $rol->value, 'permiso' => Permiso::GESTIONAR_AJUSTES->value],
                ['created_at' => $ahora, 'updated_at' => $ahora],
            );
        }
    }

    public function down(): void
    {
        DB::table('permisos_de_rol')
            ->where('permiso', Permiso::GESTIONAR_AJUSTES->value)
            ->delete();
    }
};
