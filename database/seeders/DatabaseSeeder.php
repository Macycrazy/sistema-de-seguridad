<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Un usuario por rol, para poder entrar. El «Test User» que traía Laravel se fue con el
        // correo: aquí no se registra el de nadie.
        $this->call(UsuariosSeeder::class);

        // Personal inventado para poder usar la pantalla de marcar en desarrollo.
        $this->call(TrabajadoresSeeder::class);

        // Fotos de mentira para algunos de ellos, para ver la pantalla con foto y sin ella.
        $this->call(FotosInventadasSeeder::class);
    }
}
