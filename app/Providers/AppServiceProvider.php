<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Permisos;
use App\Services\Registro\FuenteDelRegistro;
use App\Services\Registro\RegistroReal;
use App\Usuarios\Permiso;
use App\Usuarios\Rol;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // De dónde saca sus datos el registro (parte 2).
        //
        // Ya lee de las tablas reales (`personas` y `movimientos`): lo que marca la parte 1
        // aparece en el registro. La versión inventada (RegistroInventado) sigue existiendo
        // para las pruebas del componente, que la fijan por su cuenta.
        //
        // Singleton por consistencia con el resto; RegistroReal no guarda estado entre llamadas.
        $this->app->singleton(FuenteDelRegistro::class, RegistroReal::class);

        // Singleton también, y por lo mismo: los gates preguntan aquí en cada comprobación, y sin
        // esto sería una consulta por cada botón de cada pantalla.
        $this->app->singleton(Permisos::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->definirPermisos();

        /*
         * Livewire manda sus acciones a su propia ruta, no a la de la pantalla, y solo vuelve a
         * aplicar los middleware que le digan que son persistentes. Sin esto, «can:...» cerraría
         * la puerta de entrada a una pantalla pero no la de sus acciones: bastaría con hablarle a
         * Livewire directamente para saltársela.
         *
         * Livewire ya trae «Authorize» en su lista por omisión; se nombra aquí para que se vea, y
         * para que quitarlo de allí algún día no nos deje callados.
         *
         * Aun así, las acciones que importan vuelven a revisar el permiso por dentro. Un mecanismo
         * del framework no es sitio para apoyar la única defensa que hay.
         */
        Livewire::addPersistentMiddleware([
            Authorize::class,
        ]);
    }

    /**
     * Los gates del sistema.
     *
     * Uno por cada caso de Permiso, y quién lo tiene sale de la tabla «permisos_de_rol», que el
     * administrador cambia desde la pantalla de roles. Aquí no hay ninguna regla escrita a mano:
     * la que había antes es ahora la columna «por omisión» del enum, y la sembró la migración.
     *
     * Lo que sigue siendo código, y a propósito, es el orden de los roles: a quién puede tocar
     * cada quien lo dice Rol::alcanza(), no esta tabla. Ver App\Usuarios\Permiso.
     */
    private function definirPermisos(): void
    {
        foreach (Permiso::cases() as $permiso) {
            Gate::define(
                $permiso->value,
                fn (User $usuario) => $usuario->rol instanceof Rol
                    && app(Permisos::class)->tiene($usuario->rol, $permiso),
            );
        }
    }
}
