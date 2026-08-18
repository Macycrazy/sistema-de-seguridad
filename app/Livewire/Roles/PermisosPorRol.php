<?php

namespace App\Livewire\Roles;

use App\Services\Auditoria\Auditoria;
use App\Services\Permisos;
use App\Usuarios\Permiso;
use App\Usuarios\Rol;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Qué puede hacer cada rol. Solo el administrador.
 *
 * Una casilla por cruce de rol y permiso. Lo que se marque aquí es lo que abre cada pantalla del
 * sistema, sin tocar código ni volver a desplegar.
 *
 * Lo que esta pantalla NO cambia —y conviene tenerlo claro antes de usarla— es el orden de los
 * roles: darle «gestionar usuarios» al vigilante le deja crear vigilantes, no administradores.
 * A quién puede tocar cada quien lo decide Rol::alcanza(), que es código.
 */
class PermisosPorRol extends Component
{
    /** @var array<string, array<string, bool>>  [rol][permiso] => marcada */
    public array $matriz = [];

    /** No es propiedad de Livewire: al ser protegida no viaja al navegador. */
    protected Permisos $permisos;

    public string $confirmacion = '';

    public function boot(): void
    {
        // En «boot» y no en «mount»: las acciones posteriores rehidratan el componente sin volver
        // a montarlo, y a quien le quiten el rol con la pantalla abierta se le corta aquí.
        Gate::authorize('gestionar-permisos');

        $this->permisos = app(Permisos::class);
    }

    public function mount(): void
    {
        $this->leerDeLaBase();
    }

    /** @return array<int, Rol> */
    public function roles(): array
    {
        return Rol::cases();
    }

    /** @return array<int, Permiso> */
    public function permisos(): array
    {
        return Permiso::cases();
    }

    /**
     * Si esa casilla se puede tocar.
     *
     * «Gestionar permisos» está clavado en administrador: quitárselo cerraría esta pantalla para
     * siempre, y dárselo a otro rol le dejaría concederse todo lo demás en dos clics.
     */
    public function editable(Rol $rol, Permiso $permiso): bool
    {
        return ! $permiso->esIntocable();
    }

    public function guardar(): void
    {
        $this->confirmacion = '';
        $this->resetErrorBag();

        try {
            foreach (Rol::cases() as $rol) {
                $this->permisos->guardar($rol, $this->marcadosDe($rol), auth()->user());
            }
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->leerDeLaBase();

        app(Auditoria::class)->cambioPermisos();

        $this->confirmacion = 'Permisos guardados. Valen desde ya, sin volver a entrar.';
    }

    public function restablecer(): void
    {
        $this->confirmacion = '';
        $this->resetErrorBag();

        try {
            $this->permisos->restablecer(auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->leerDeLaBase();

        $this->confirmacion = 'Permisos devueltos a como venían de fábrica.';
    }

    public function render()
    {
        return view('livewire.roles.permisos-por-rol');
    }

    /**
     * Lo marcado para un rol.
     *
     * Se filtra contra los casos del enum a propósito: por Livewire puede llegar cualquier clave
     * en la matriz, y una que no sea un permiso del sistema no tiene por qué entrar en la base.
     *
     * @return array<int, Permiso>
     */
    protected function marcadosDe(Rol $rol): array
    {
        return array_values(array_filter(
            Permiso::cases(),
            fn (Permiso $permiso) => (bool) ($this->matriz[$rol->value][$permiso->value] ?? false),
        ));
    }

    protected function leerDeLaBase(): void
    {
        $this->matriz = [];

        foreach (Rol::cases() as $rol) {
            foreach (Permiso::cases() as $permiso) {
                $this->matriz[$rol->value][$permiso->value] = $this->permisos->tiene($rol, $permiso);
            }
        }
    }
}
