<?php

namespace Tests\Feature\Seeders;

use App\Models\Persona;
use Database\Seeders\TrabajadoresDesdeCarnetsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrabajadoresDesdeCarnetsSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sin_conexion_configurada_no_siembra_ni_revienta(): void
    {
        config(['database.connections.carnets.database' => null]);

        $this->seed(TrabajadoresDesdeCarnetsSeeder::class);

        $this->assertSame(0, Persona::count());
    }

    #[Test]
    public function trae_a_los_trabajadores_reales_de_carnets(): void
    {
        $this->montarBaseDeCarnets();

        DB::table('Department')->insert(['id' => 1, 'name' => 'Tecnología']);
        DB::table('Carnets')->insert([
            // Activo, con gerencia y nacionalidad E.
            ['name' => 'Ana', 'lastname' => 'Rodríguez', 'cedule' => '12.345.678', 'identifier' => 'E', 'id_status' => 1, 'id_department' => 1],
            // Inactivo (estado distinto de 1): se registra pero no podrá marcar.
            ['name' => 'Luis', 'lastname' => 'Pérez', 'cedule' => '87654321', 'identifier' => 'V', 'id_status' => 2, 'id_department' => null],
        ]);

        $this->seed(TrabajadoresDesdeCarnetsSeeder::class);

        $ana = Persona::where('cedula', '12345678')->firstOrFail();
        $this->assertTrue($ana->esTrabajador());
        $this->assertSame('Ana Rodríguez', $ana->nombre);
        $this->assertSame('E', $ana->nacionalidad);
        $this->assertSame('Tecnología', $ana->dependencia);
        $this->assertTrue($ana->activo);

        $luis = Persona::where('cedula', '87654321')->firstOrFail();
        $this->assertFalse($luis->activo);
    }

    #[Test]
    public function volver_a_sembrar_no_duplica(): void
    {
        $this->montarBaseDeCarnets();
        DB::table('Carnets')->insert(['name' => 'Ana', 'lastname' => 'Rodríguez', 'cedule' => '12345678', 'identifier' => 'V', 'id_status' => 1]);

        $this->seed(TrabajadoresDesdeCarnetsSeeder::class);
        $this->seed(TrabajadoresDesdeCarnetsSeeder::class);

        $this->assertSame(1, Persona::where('cedula', '12345678')->count());
    }

    /**
     * Levanta en la MISMA base de pruebas las tablas que el seeder lee de carnets, y apunta la
     * conexión «carnets» aquí. Así se prueba el mapeo sin una segunda base de verdad.
     */
    private function montarBaseDeCarnets(): void
    {
        config(['database.connections.carnets' => config('database.connections.'.config('database.default'))]);

        // La prueba corre dentro de la transacción de RefreshDatabase: una conexión aparte no vería
        // estas tablas sin confirmar. Se hace que «carnets» use el MISMO PDO que la base de prueba.
        DB::purge('carnets');
        DB::connection('carnets')->setPdo(DB::connection()->getPdo());

        Schema::create('Department', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });

        Schema::create('Carnets', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('lastname')->nullable();
            $t->string('cedule')->nullable();
            $t->string('identifier')->default('V');
            $t->unsignedBigInteger('id_status')->nullable();
            $t->unsignedBigInteger('id_department')->nullable();
        });
    }
}
