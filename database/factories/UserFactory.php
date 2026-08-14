<?php

namespace Database\Factories;

use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** La clave de las pruebas, con la que entra cualquier usuario que salga de aquí. */
    public const CLAVE = 'clave-de-prueba';

    /** Hasheada una sola vez: aunque en pruebas BCRYPT_ROUNDS sea 4, se paga en cada fila. */
    protected static ?string $claveHasheada;

    /**
     * Por omisión sale un vigilante activo. Es el rol más común y el que menos alcanza, así que
     * una prueba que se olvide de decir el rol falla del lado seguro.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario' => fake()->unique()->userName(),
            'nombre' => fake()->name(),
            'cedula' => fake()->unique()->numerify('########'),
            'rol' => Rol::VIGILANTE,
            'activo' => true,
            'password' => static::$claveHasheada ??= Hash::make(self::CLAVE),
            'remember_token' => Str::random(10),
        ];
    }

    public function supervisor(): static
    {
        return $this->state(fn () => ['rol' => Rol::SUPERVISOR]);
    }

    public function administrador(): static
    {
        return $this->state(fn () => ['rol' => Rol::ADMINISTRADOR]);
    }

    public function desactivado(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
