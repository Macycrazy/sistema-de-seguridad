<?php

use App\Usuarios\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el permiso «gestionar-respaldos» para las bases que ya se migraron.
 *
 * Como los otros permisos: una base nueva lo trae de permisos_de_rol al leer el enum; ésta es
 * para las que se crearon antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        foreach (Permiso::GESTIONAR_RESPALDOS->porOmision() as $rol) {
            DB::table('permisos_de_rol')->updateOrInsert(
                ['rol' => $rol->value, 'permiso' => Permiso::GESTIONAR_RESPALDOS->value],
                ['created_at' => $ahora, 'updated_at' => $ahora],
            );
        }
    }

    public function down(): void
    {
        DB::table('permisos_de_rol')
            ->where('permiso', Permiso::GESTIONAR_RESPALDOS->value)
            ->delete();
    }
};
