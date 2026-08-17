<?php

namespace App\Services;

use App\Models\User;
use App\Usuarios\Permiso;
use App\Usuarios\Rol;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quién puede hacer qué. Lo lee de la base, no del código.
 *
 * Es un singleton y se lee una sola vez por petición: los gates preguntan aquí en cada
 * comprobación, y una consulta por comprobación sería una consulta por cada botón de cada
 * pantalla.
 *
 * Lo que aquí NO se decide es a quién puede tocar cada quien. Eso es Rol::alcanza(), es código, y
 * no se toca desde ninguna pantalla: un supervisor con «gestionar usuarios» sigue sin poder ponerle
 * la clave a un administrador. Si esa regla fuera configurable, la pantalla de permisos sería una
 * escalera al rol de arriba.
 */
class Permisos
{
    /** @var array<string, array<string, true>>|null  [rol][permiso] => true */
    private ?array $concedidos = null;

    public function tiene(Rol $rol, Permiso $permiso): bool
    {
        return isset($this->cargados()[$rol->value][$permiso->value]);
    }

    /**
     * Los permisos de un rol, para pintar la pantalla.
     *
     * @return array<int, Permiso>
     */
    public function de(Rol $rol): array
    {
        return array_values(array_filter(
            Permiso::cases(),
            fn (Permiso $permiso) => $this->tiene($rol, $permiso),
        ));
    }

    /**
     * Deja un rol exactamente con los permisos que se le pasen.
     *
     * @param  array<int, Permiso>  $permisos
     *
     * @throws ValidationException
     */
    public function guardar(Rol $rol, array $permisos, User $quienLoHace): void
    {
        // Cinturón además del gate de la pantalla: este servicio también lo puede llamar otra
        // cosa, y quedarse sin quien administre los permisos no se arregla desde ninguna pantalla.
        if (! $quienLoHace->esAdministrador()) {
            throw ValidationException::withMessages([
                'permisos' => 'Solo un administrador cambia los permisos.',
            ]);
        }

        $valores = collect($permisos)
            ->reject(fn (Permiso $permiso) => $permiso->esIntocable())
            ->unique()
            ->values();

        /*
         * «Gestionar permisos» no se negocia: se lo queda el administrador y no lo tiene nadie
         * más, venga lo que venga en la petición. Quitárselo cerraría esta pantalla para siempre;
         * dárselo a otro rol le dejaría concederse a sí mismo todo lo demás en dos clics.
         */
        if ($rol === Rol::ADMINISTRADOR) {
            $valores->push(Permiso::GESTIONAR_PERMISOS);
        }

        DB::transaction(function () use ($rol, $valores) {
            DB::table('permisos_de_rol')->where('rol', $rol->value)->delete();

            if ($valores->isEmpty()) {
                return;
            }

            $ahora = now();

            DB::table('permisos_de_rol')->insert(
                $valores->map(fn (Permiso $permiso) => [
                    'rol' => $rol->value,
                    'permiso' => $permiso->value,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->all(),
            );
        });

        $this->olvidar();
    }

    /** Vuelve a dejar los permisos como venían de fábrica. */
    public function restablecer(User $quienLoHace): void
    {
        foreach (Rol::cases() as $rol) {
            $this->guardar(
                $rol,
                array_values(array_filter(
                    Permiso::cases(),
                    fn (Permiso $permiso) => in_array($rol, $permiso->porOmision(), true),
                )),
                $quienLoHace,
            );
        }
    }

    /** Para después de escribir: la siguiente lectura vuelve a la base. */
    public function olvidar(): void
    {
        $this->concedidos = null;
    }

    /** @return array<string, array<string, true>> */
    private function cargados(): array
    {
        if ($this->concedidos !== null) {
            return $this->concedidos;
        }

        $this->concedidos = [];

        foreach (DB::table('permisos_de_rol')->get(['rol', 'permiso']) as $fila) {
            $this->concedidos[$fila->rol][$fila->permiso] = true;
        }

        return $this->concedidos;
    }
}
