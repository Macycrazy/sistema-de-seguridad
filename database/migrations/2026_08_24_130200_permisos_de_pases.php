<?php

use App\Usuarios\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Siembra los permisos de los pases de visitante en las bases que ya se migraron.
 *
 * Igual que los demás: una base nueva los trae de la migración de permisos_de_rol al leer el enum;
 * ésta es para las que se crearon antes de que existieran.
 */
return new class extends Migration
{
    private const NUEVOS = [Permiso::VER_PASES, Permiso::GESTIONAR_PASES];

    public function up(): void
    {
        $ahora = now();

        foreach (self::NUEVOS as $permiso) {
            foreach ($permiso->porOmision() as $rol) {
                DB::table('permisos_de_rol')->updateOrInsert(
                    ['rol' => $rol->value, 'permiso' => $permiso->value],
                    ['created_at' => $ahora, 'updated_at' => $ahora],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('permisos_de_rol')
            ->whereIn('permiso', array_map(fn (Permiso $p) => $p->value, self::NUEVOS))
            ->delete();
    }
};
