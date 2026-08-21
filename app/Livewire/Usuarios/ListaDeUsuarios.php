<?php

namespace App\Livewire\Usuarios;

use App\Models\User;
use App\Services\GestionDeUsuarios;
use App\Usuarios\Rol;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La pantalla del administrador: dar de alta, desactivar y cambiar claves.
 *
 * No decide nada por su cuenta: todo se lo pregunta a GestionDeUsuarios, que es donde se valida
 * en el servidor.
 *
 * Las claves las teclea siempre el administrador. El sistema no inventa ninguna: si la generara,
 * habría que enseñarla en pantalla para poder dictarla, y una clave escrita en la pantalla de un
 * puesto de vigilancia la lee cualquiera que pase por detrás.
 *
 * La vía preferida para quitar a alguien es desactivarlo, para que el rastro que dejó siga
 * apuntando a él. Borrar existe —para las cuentas creadas por error— pero anula ese rastro, así
 * que va aparte y con confirmación.
 */
class ListaDeUsuarios extends Component
{
    /** El formulario de alta empieza cerrado: la pantalla se abre para mirar, no para crear. */
    public bool $creando = false;

    /** A quién se está editando; null cuando el formulario es un alta nueva. */
    public ?int $editandoId = null;

    public string $usuario = '';

    public string $nombre = '';

    public string $cedula = '';

    public string $rol = '';

    /** La clave del alta. La pone el administrador y se la dicta a su dueño. */
    public string $clave = '';

    /** A quién se le está cambiando la clave desde la lista. */
    public ?int $cambiandoClaveA = null;

    public string $claveNueva = '';

    /** A quién se le está cambiando el rol desde la lista. */
    public ?int $cambiandoRolA = null;

    public string $rolNuevo = '';

    /**
     * Lo que se dice después de tocar una clave.
     *
     * Es un aviso, no la clave: la escribió el administrador, ya la sabe, y repetírsela en
     * pantalla solo serviría para que la leyera quien pasara por detrás.
     */
    public string $aviso = '';

    /** No es propiedad de Livewire: al ser protegida no viaja al navegador. */
    protected GestionDeUsuarios $gestion;

    public function boot(): void
    {
        /*
         * El permiso, en «boot» y no en «mount»: «mount» corre una sola vez y las acciones
         * posteriores rehidratan el componente sin volver a montarlo. Al administrador al que le
         * bajen el rol con la pantalla abierta se le corta aquí mismo.
         */
        // Para ENTRAR basta con ver; dar de alta, editar o borrar exige «gestionar» aparte.
        Gate::authorize('ver-usuarios');

        $this->gestion = app(GestionDeUsuarios::class);
    }

    public function mount(): void
    {
        $this->rol = Rol::VIGILANTE->value;
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function usuarios(): Collection
    {
        return User::query()
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Los roles que quien está mirando puede repartir: los suyos y los de abajo.
     *
     * Un supervisor no ve «Administrador» en el selector. Es cortesía, no seguridad — el servicio
     * lo corta igual si llega por Livewire.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function roles(): array
    {
        return collect(Rol::cases())
            ->filter(fn (Rol $rol) => auth()->user()->alcanza($rol))
            ->mapWithKeys(fn (Rol $rol) => [$rol->value => $rol->etiqueta()])
            ->all();
    }

    /**
     * Si quien está mirando puede tocar esa fila.
     *
     * Solo sirve para no dibujar botones que van a dar error. Quien mande la acción igual se topa
     * con el servicio.
     */
    public function puedeGestionar(User $fila): bool
    {
        return auth()->user()->alcanza($fila->rol);
    }

    /** Cambiar usuarios es aparte de verlos: quien solo puede ver entra, pero no toca nada. */
    protected function exigirGestion(): void
    {
        Gate::authorize('gestionar-usuarios');
    }

    public function abrirFormulario(): void
    {
        $this->exigirGestion();

        $this->olvidarLoAnterior();
        $this->creando = true;
    }

    public function cerrarFormulario(): void
    {
        $this->limpiarFormulario();
        $this->creando = false;
    }

    /** Carga a un usuario en el formulario para corregir sus datos (nombre, usuario, cédula). */
    public function editar(int $id): void
    {
        $this->exigirGestion();

        $this->olvidarLoAnterior();

        $usuario = $this->encontrar($id);
        $this->editandoId = $usuario->id;
        $this->usuario = $usuario->usuario;
        $this->nombre = $usuario->nombre;
        $this->cedula = (string) $usuario->cedula;
        $this->creando = true;
    }

    /** El botón del formulario: crea si es nuevo, o guarda la corrección si se está editando. */
    public function guardar(): void
    {
        $this->exigirGestion();

        if ($this->editandoId !== null) {
            $this->guardarEdicion();

            return;
        }

        $this->crear();
    }

    private function guardarEdicion(): void
    {
        $this->aviso = '';
        $this->resetErrorBag();

        try {
            $usuario = $this->gestion->editar(
                usuario: $this->encontrar($this->editandoId),
                nombre: $this->nombre,
                nombreDeUsuario: $this->usuario,
                cedula: $this->cedula,
                quienLoHace: auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->aviso = "Datos de «{$usuario->usuario}» actualizados.";
        $this->limpiarFormulario();
        $this->creando = false;
        unset($this->usuarios);
    }

    public function eliminar(int $id): void
    {
        $this->exigirGestion();

        $this->olvidarLoAnterior();

        try {
            $this->gestion->eliminar($this->encontrar($id), auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->aviso = 'Usuario borrado.';
        unset($this->usuarios);
    }

    public function crear(): void
    {
        $this->exigirGestion();

        $this->olvidarLoAnterior();

        $rol = Rol::tryFrom($this->rol);

        // El selector de la pantalla solo ofrece los tres, pero por Livewire puede llegar
        // cualquier cosa: se revisa en el servidor, como todo.
        if ($rol === null) {
            $this->addError('rol', 'Ese rol no existe en el sistema.');

            return;
        }

        try {
            $creado = $this->gestion->crear(
                usuario: $this->usuario,
                nombre: $this->nombre,
                cedula: $this->cedula,
                rol: $rol,
                clave: $this->clave,
                quienLoHace: auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->aviso = "Usuario «{$creado->usuario}» creado. Ya puede entrar con la clave que le pusiste.";

        $this->limpiarFormulario();
        $this->creando = false;
        unset($this->usuarios);
    }

    /** Abre el campo para teclearle una clave nueva a alguien de la lista. */
    public function abrirCambioDeClave(int $id): void
    {
        $this->exigirGestion();

        $this->olvidarLoAnterior();
        $this->cambiandoClaveA = $id;
    }

    public function cerrarCambioDeClave(): void
    {
        $this->cambiandoClaveA = null;
        $this->claveNueva = '';
        $this->resetErrorBag();
    }

    public function guardarCambioDeClave(): void
    {
        $this->exigirGestion();

        if ($this->cambiandoClaveA === null) {
            return;
        }

        $usuario = $this->encontrar($this->cambiandoClaveA);

        try {
            $this->gestion->ponerClave($usuario, $this->claveNueva, auth()->user(), campo: 'claveNueva');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->cerrarCambioDeClave();

        $this->aviso = "Clave cambiada para {$usuario->nombre}. Con esa entra desde ahora.";
    }

    public function desactivar(int $id): void
    {
        $this->exigirGestion();

        $this->olvidarLoAnterior();

        try {
            $this->gestion->desactivar($this->encontrar($id), auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->usuarios);
    }

    public function reactivar(int $id): void
    {
        $this->exigirGestion();

        $this->olvidarLoAnterior();

        try {
            $this->gestion->reactivar($this->encontrar($id), auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->usuarios);
    }

    /** Abre el selector de rol de esa fila. */
    public function abrirCambioDeRol(int $id): void
    {
        $this->exigirGestion();

        $this->olvidarLoAnterior();

        $this->cambiandoRolA = $id;
        $this->rolNuevo = $this->encontrar($id)->rol->value;
    }

    public function cerrarCambioDeRol(): void
    {
        $this->cambiandoRolA = null;
        $this->rolNuevo = '';
        $this->resetErrorBag();
    }

    public function guardarCambioDeRol(): void
    {
        $this->exigirGestion();

        if ($this->cambiandoRolA === null) {
            return;
        }

        $usuario = $this->encontrar($this->cambiandoRolA);
        $rol = Rol::tryFrom($this->rolNuevo);

        if ($rol === null) {
            $this->addError('rol', 'Ese rol no existe en el sistema.');

            return;
        }

        try {
            $this->gestion->cambiarRol($usuario, $rol, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->cerrarCambioDeRol();

        $this->aviso = "{$usuario->nombre} pasa a ser {$rol->etiqueta()}.";

        unset($this->usuarios);
    }

    public function render()
    {
        return view('livewire.usuarios.lista-de-usuarios');
    }

    /** El id viene del navegador, así que se busca aquí y se falla con un 404 si no está. */
    protected function encontrar(int $id): User
    {
        return User::findOrFail($id);
    }

    /** En cuanto se hace otra cosa, no queda ni el aviso ni ningún campo abierto. */
    protected function olvidarLoAnterior(): void
    {
        $this->aviso = '';
        $this->cambiandoClaveA = null;
        $this->claveNueva = '';
        $this->cambiandoRolA = null;
        $this->rolNuevo = '';
        $this->editandoId = null;
        $this->resetErrorBag();
    }

    protected function limpiarFormulario(): void
    {
        $this->usuario = '';
        $this->nombre = '';
        $this->cedula = '';
        $this->clave = '';
        $this->rol = Rol::VIGILANTE->value;
        $this->editandoId = null;
        $this->resetErrorBag();
    }
}
