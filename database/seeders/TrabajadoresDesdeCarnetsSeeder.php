<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Trae a TODO el personal del edificio desde el sistema de carnets, para que «Control de Acceso
 * Unificado» los reconozca como trabajadores (y no como invitados) sin teclearlos a mano.
 *
 * A diferencia de TrabajadoresSeeder —que inventa gente para probar en desarrollo—, este lee la
 * gente REAL directo de la base de carnets, por la conexión «carnets» (config/database.php). Los
 * dos sistemas viven en el mismo servidor, así que esa base está al lado.
 *
 * Es de correr A MANO en el servidor, no en el sembrado por omisión:
 *
 *     php artisan db:seed --class=Database\\Seeders\\TrabajadoresDesdeCarnetsSeeder
 *
 * Se puede correr las veces que haga falta: cada trabajador se busca por su cédula y se actualiza,
 * no se duplica. Si la conexión a carnets no está configurada (CARNETS_DB_DATABASE vacío) o no
 * responde, avisa y se salta —no rompe el resto del sembrado ni el despliegue—.
 */
class TrabajadoresDesdeCarnetsSeeder extends Seeder
{
    public function run(): void
    {
        if (blank(config('database.connections.carnets.database'))) {
            $this->command?->warn('Sin CARNETS_DB_DATABASE en el .env: no se sembró el personal desde carnets.');

            return;
        }

        try {
            // Un carnet por persona con su gerencia (Department) y su estado (Status=1 → activo).
            // Se lee de la base de carnets tal como la arma su propia verificación pública.
            $carnets = DB::connection('carnets')
                ->table('Carnets')
                ->leftJoin('Department', 'Carnets.id_department', '=', 'Department.id')
                ->whereNotNull('Carnets.cedule')
                ->select([
                    'Carnets.name',
                    'Carnets.lastname',
                    'Carnets.cedule',
                    'Carnets.identifier',
                    'Carnets.id_status',
                    'Department.name as gerencia',
                ])
                ->get();
        } catch (Throwable $e) {
            $this->command?->error('No se pudo leer la base de carnets: '.$e->getMessage());

            return;
        }

        $sembrados = 0;
        $saltados = 0;

        foreach ($carnets as $c) {
            $cedula = Persona::normalizarCedula($c->cedule);

            // Sin cédula utilizable no hay a quién marcar: ese carnet se salta.
            if ($cedula === '') {
                $saltados++;

                continue;
            }

            Persona::updateOrCreate(
                ['cedula' => $cedula],
                [
                    'tipo' => Persona::TRABAJADOR,
                    'nacionalidad' => Persona::normalizarNacionalidad($c->identifier),
                    'nombre' => trim(($c->name ?? '').' '.($c->lastname ?? '')) ?: 'SIN NOMBRE',
                    // En carnets la gerencia es «Department»; aquí la columna se llama «dependencia»
                    // (en pantalla se lee «Gerencia»). Ver TrabajadoresSeeder.
                    'dependencia' => $c->gerencia ?: null,
                    // El ente no lo guarda carnets por trabajador: se asume CIIP, el organismo
                    // principal del edificio. Se ajusta a mano después si hace falta.
                    'ente' => Persona::ENTE_CIIP,
                    // En carnets el estado 1 es «activo». Cualquier otro deja al trabajador
                    // registrado pero sin poder marcar, igual que un carnet vencido.
                    'activo' => (int) $c->id_status === 1,
                ],
            );

            $sembrados++;
        }

        $this->command?->info("Personal desde carnets: {$sembrados} sembrados"
            .($saltados > 0 ? ", {$saltados} saltados (sin cédula)" : '').'.');
    }
}
