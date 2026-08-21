<?php

namespace App\Livewire\Roles;

use App\Services\Auditoria\Auditoria;
use App\Services\GestionDeRoles;
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

    /** El formulario de alta/edición de un rol. Empieza cerrado: la pantalla se abre para marcar. */
    public bool $gestionandoRol = false;

    /** Slug del rol que se está editando; null cuando el formulario es un alta nueva. */
    public ?string $rolEditando = null;

    public string $nombreRol = '';

    /** Nivel del rol nuevo (1/2/3). Llega como texto desde el select. */
    public string $nivelRol = '1';

    /** No son propiedades de Livewire: al ser protegidas no viajan al navegador. */
    protected Permisos $permisos;

    protected GestionDeRoles $gestionRoles;

    public string $confirmacion = '';

    public function boot(): void
    {
        // En «boot» y no en «mount»: las acciones posteriores rehidratan el componente sin volver
        // a montarlo, y a quien le quiten el rol con la pantalla abierta se le corta aquí.
        Gate::authorize('gestionar-permisos');

        $this->permisos = app(Permisos::class);
        $this->gestionRoles = app(GestionDeRoles::class);
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
     * Los permisos agrupados por módulo, para pintar la pantalla con un encabezado por grupo.
     *
     * @return array<string, array<int, Permiso>>
     */
    public function porGrupo(): array
    {
        $grupos = [];

        foreach (Permiso::cases() as $permiso) {
            $grupos[$permiso->grupo()][] = $permiso;
        }

        return $grupos;
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

    /**
     * El «ver» de un módulo se bloquea (marcado y sin poder tocar) cuando su «gestionar» está
     * activo: gestionar implica ver, así que quitarle el ver a quien puede gestionar no significa
     * nada. Se enseña marcado para que se entienda, y se deja fijo para que no confunda.
     */
    public function bloqueada(Rol $rol, Permiso $permiso): bool
    {
        $implicador = $permiso->implicadoPor();

        return $implicador !== null && ($this->matriz[$rol->value][$implicador->value] ?? false);
    }

    /** Al marcar un «gestionar», su «ver» se enciende solo: gestionar implica ver. */
    public function updatedMatriz(): void
    {
        $this->aplicarImplicaciones();
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

    /**
     * Los niveles que se le pueden dar a un rol nuevo, con a qué base equivale cada uno.
     *
     * @return array<int, string>
     */
    public function niveles(): array
    {
        return [
            1 => 'Nivel 1 · como Vigilante',
            2 => 'Nivel 2 · como Supervisor',
            3 => 'Nivel 3 · como Administrador',
        ];
    }

    public function abrirNuevoRol(): void
    {
        $this->reset('rolEditando', 'nombreRol', 'confirmacion');
        $this->nivelRol = '1';
        $this->resetErrorBag();
        $this->gestionandoRol = true;
    }

    public function abrirEdicionRol(string $slug): void
    {
        $rol = Rol::desde($slug);

        if ($rol === null || $rol->esBase()) {
            return;
        }

        $this->rolEditando = $rol->value;
        $this->nombreRol = $rol->nombre;
        $this->nivelRol = (string) $rol->nivel;
        $this->reset('confirmacion');
        $this->resetErrorBag();
        $this->gestionandoRol = true;
    }

    public function cancelarRol(): void
    {
        $this->reset('rolEditando', 'nombreRol', 'gestionandoRol');
        $this->nivelRol = '1';
        $this->resetErrorBag();
    }

    public function guardarRol(): void
    {
        $this->resetErrorBag();
        $this->confirmacion = '';

        try {
            if ($this->rolEditando === null) {
                $rol = $this->gestionRoles->crear($this->nombreRol, (int) $this->nivelRol, auth()->user());
                $mensaje = "Rol «{$rol->nombre}» creado. Márcale abajo lo que puede hacer.";
            } else {
                $rol = Rol::desde($this->rolEditando);

                if ($rol === null) {
                    return;
                }

                $this->gestionRoles->editar($rol, $this->nombreRol, (int) $this->nivelRol, auth()->user());
                $mensaje = 'Rol actualizado.';
            }
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->cancelarRol();
        $this->leerDeLaBase();
        $this->confirmacion = $mensaje;
    }

    public function eliminarRol(string $slug): void
    {
        $this->resetErrorBag();
        $this->confirmacion = '';

        $rol = Rol::desde($slug);

        if ($rol === null) {
            return;
        }

        try {
            $this->gestionRoles->eliminar($rol, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->leerDeLaBase();
        $this->confirmacion = 'Rol borrado.';
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

    /** Deja marcado el «ver» de todo módulo cuyo «gestionar» esté marcado. Gestionar implica ver. */
    protected function aplicarImplicaciones(): void
    {
        foreach (Rol::cases() as $rol) {
            foreach (Permiso::cases() as $permiso) {
                $implicador = $permiso->implicadoPor();

                if ($implicador !== null && ($this->matriz[$rol->value][$implicador->value] ?? false)) {
                    $this->matriz[$rol->value][$permiso->value] = true;
                }
            }
        }
    }
}
