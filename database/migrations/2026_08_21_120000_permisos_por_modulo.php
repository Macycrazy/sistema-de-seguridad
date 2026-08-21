<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Parte los permisos por módulo: aparecen «ver-*» y «gestionar-*» separados, y dos módulos que
 * antes iban montados sobre otro pasan a tener permiso propio:
 *
 *   - El ORGANIGRAMA se gestionaba con «gestionar-personal».
 *   - Los PUESTOS del estacionamiento se gestionaban con «gestionar-edificio».
 *
 * Para no quitarle acceso a nadie, esta migración le da a cada rol el permiso nuevo si ya tenía el
 * que lo cubría. Los «ver-*» no hacen falta en la base: gestionar implica ver (Permiso::implicadoPor
 * y Permisos::tiene), así que quien tenga «gestionar-*» ya puede ver su módulo.
 */
return new class extends Migration
{
    /** [permiso nuevo => permiso que antes lo cubría] */
    private const HEREDA = [
        'gestionar-organigrama' => 'gestionar-personal',
        'gestionar-puestos' => 'gestionar-edificio',
    ];

    public function up(): void
    {
        $ahora = now();

        foreach (self::HEREDA as $nuevo => $cubria) {
            $roles = DB::table('permisos_de_rol')
                ->where('permiso', $cubria)
                ->pluck('rol');

            foreach ($roles as $rol) {
                DB::table('permisos_de_rol')->updateOrInsert(
                    ['rol' => $rol, 'permiso' => $nuevo],
                    ['created_at' => $ahora, 'updated_at' => $ahora],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('permisos_de_rol')
            ->whereIn('permiso', array_keys(self::HEREDA))
            ->delete();
    }
};
