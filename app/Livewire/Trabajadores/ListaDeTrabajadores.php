<?php

namespace App\Livewire\Trabajadores;

use App\Exports\PlantillaTrabajadores;
use App\Imports\TrabajadoresImport;
use App\Models\Persona;
use App\Services\Auditoria\Auditoria;
use App\Services\GestionDeInvitados;
use App\Services\GestionDeTrabajadores;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La pantalla para meter al personal: uno a uno, o en bloque desde un Excel.
 *
 * Mientras la asociación con el sistema de carnets no exista, es por aquí por donde entra la
 * nómina. No decide nada: se lo pregunta todo a GestionDeTrabajadores, donde se valida en el
 * servidor. Un trabajador no se borra; se desactiva, y su histórico queda.
 */
class ListaDeTrabajadores extends Component
{
    use WithFileUploads;
    use WithPagination;

    /**
     * Qué se está mirando: el personal de nómina o las visitas. La pantalla es una sola; el filtro
     * cambia la lista, las columnas y el formulario. Los invitados no se crean aquí (nacen en la
     * puerta), solo se corrigen.
     */
    public string $filtro = Persona::TRABAJADOR;

    /** El formulario empieza cerrado: la pantalla se abre para mirar, no para crear ni editar. */
    public bool $creando = false;

    /** A quién se está editando; null cuando el formulario es un alta nueva. */
    public ?int $editandoId = null;

    public string $cedula = '';

    public string $nombre = '';

    public string $nacionalidad = Persona::VENEZOLANO;

    public string $ente = '';

    public string $dependencia = '';

    public string $piso = '';

    /** Solo para invitados: el motivo de la visita. */
    public string $motivo = '';

    /** El Excel a importar. */
    public $archivo = null;

    public string $busqueda = '';

    /** Filtros de la lista. Vacío = sin filtrar por ese criterio. */
    public string $filtroEnte = '';

    public string $filtroGerencia = '';

    /** Estado: '', 'activo' o 'inactivo'. */
    public string $filtroEstado = '';

    /** Lo que se dice después de guardar o importar. */
    public string $aviso = '';

    /** @var array<int, string> Errores por fila de la última importación. */
    public array $erroresDeImportacion = [];

    protected GestionDeTrabajadores $gestion;

    protected GestionDeInvitados $invitadosGestion;

    public function boot(): void
    {
        // El permiso en «boot» y no en «mount»: las acciones rehidratan sin volver a montar, así
        // que a quien le quiten el permiso con la pantalla abierta se le corta aquí mismo.
        Gate::authorize('gestionar-personal');

        $this->gestion = app(GestionDeTrabajadores::class);
        $this->invitadosGestion = app(GestionDeInvitados::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEnte(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroGerencia(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    /** Al cambiar entre personal y visitas: se cierra el formulario, se limpian filtros y se vuelve al inicio. */
    public function updatedFiltro(): void
    {
        $this->cancelarAlta();
        // Ente y gerencia son de nómina: no tienen sentido sobre las visitas.
        $this->reset('filtroEnte', 'filtroGerencia', 'filtroEstado');
        $this->resetPage();
    }

    /** Deja los filtros en blanco sin cambiar de pestaña. */
    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'filtroEnte', 'filtroGerencia', 'filtroEstado');
        $this->resetPage();
    }

    /** Si el filtro mira a las visitas. */
    public function verInvitados(): bool
    {
        return $this->filtro === Persona::INVITADO;
    }

    #[Computed]
    public function personas(): LengthAwarePaginator
    {
        $aguja = trim($this->busqueda);
        $tipo = $this->verInvitados() ? Persona::INVITADO : Persona::TRABAJADOR;

        return Persona::query()
            ->where('tipo', $tipo)
            ->when($aguja !== '', function ($q) use ($aguja) {
                $soloDigitos = preg_replace('/\D/', '', $aguja);

                $q->where(function ($q) use ($aguja, $soloDigitos) {
                    // Sin distinguir mayúsculas, y sin «ilike»: ese operador es de PostgreSQL y
                    // en SQLite —donde corren las pruebas— es un error de sintaxis. «lower()» lo
                    // entienden las dos, y hace exactamente lo mismo que hacía ilike.
                    $q->whereRaw('lower(nombre) like ?', ['%'.mb_strtolower($aguja).'%']);

                    if ($soloDigitos !== '') {
                        $q->orWhere('cedula', 'like', '%'.$soloDigitos.'%');
                    }
                });
            })
            // Ente y gerencia solo aplican a la nómina; sobre las visitas se ignoran.
            ->when(! $this->verInvitados() && $this->filtroEnte !== '', fn ($q) => $q->where('ente', $this->filtroEnte))
            ->when(! $this->verInvitados() && $this->filtroGerencia !== '', fn ($q) => $q->where('dependencia', $this->filtroGerencia))
            ->when($this->filtroEstado === 'activo', fn ($q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivo', fn ($q) => $q->where('activo', false))
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->paginate(12);
    }

    #[Computed]
    public function entes(): array
    {
        return GestionDeTrabajadores::ENTES;
    }

    /**
     * Las gerencias que de verdad hay entre los trabajadores, para llenar el desplegable. Se sacan
     * de los datos —no de un catálogo fijo— así solo se ofrece lo que existe.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function gerencias(): array
    {
        return Persona::query()
            ->where('tipo', Persona::TRABAJADOR)
            ->whereNotNull('dependencia')
            ->where('dependencia', '!=', '')
            ->distinct()
            ->orderBy('dependencia')
            ->pluck('dependencia')
            ->all();
    }

    public function abrirAlta(): void
    {
        // Alta manual: solo de trabajadores. Los invitados nacen en la puerta, no aquí.
        $this->filtro = Persona::TRABAJADOR;
        $this->limpiarFormulario();
        $this->reset('aviso');
        $this->creando = true;
    }

    /** Carga a una persona en el formulario para corregir sus datos. La cédula queda fija. */
    public function editar(int $id): void
    {
        $persona = Persona::findOrFail($id);

        $this->limpiarFormulario();
        $this->editandoId = $persona->id;
        $this->filtro = $persona->tipo;
        $this->cedula = $persona->cedula;
        $this->nombre = $persona->nombre;
        $this->nacionalidad = $persona->nacionalidad ?: Persona::VENEZOLANO;
        $this->ente = (string) $persona->ente;
        $this->dependencia = (string) $persona->dependencia;
        $this->piso = (string) $persona->piso;
        $this->motivo = (string) $persona->motivo;
        $this->creando = true;
    }

    public function cancelarAlta(): void
    {
        $this->creando = false;
        $this->limpiarFormulario();
    }

    public function guardar(): void
    {
        // Si la validación del servicio falla, la ValidationException sube y Livewire la pinta
        // junto a cada campo. No hace falta atraparla.
        if ($this->verInvitados()) {
            $this->guardarInvitado();

            return;
        }

        $trabajador = $this->gestion->guardar(
            cedula: $this->cedula,
            nombre: $this->nombre,
            ente: $this->ente,
            dependencia: $this->dependencia,
            piso: $this->piso,
            nacionalidad: $this->nacionalidad,
        );

        $editaba = $this->editandoId !== null;
        $this->creando = false;
        $this->limpiarFormulario();
        $this->erroresDeImportacion = [];
        app(Auditoria::class)->cargoPersonal(($editaba ? 'edición · ' : 'alta manual · ').$trabajador->cedula);
        $this->aviso = $trabajador->wasRecentlyCreated
            ? 'Trabajador dado de alta.'
            : 'Datos del trabajador actualizados.';
    }

    /** El guardado de la corrección de un invitado: siempre es una edición, nunca un alta. */
    private function guardarInvitado(): void
    {
        $invitado = Persona::where('tipo', Persona::INVITADO)->findOrFail($this->editandoId);

        $this->invitadosGestion->editar(
            invitado: $invitado,
            nombre: $this->nombre,
            nacionalidad: $this->nacionalidad,
            motivo: $this->motivo,
            piso: $this->piso,
        );

        $this->creando = false;
        $this->limpiarFormulario();
        app(Auditoria::class)->cargoPersonal('edición de invitado · '.$invitado->cedula);
        $this->aviso = 'Datos del invitado actualizados.';
    }

    /** Deja el formulario en blanco y fuera del modo edición. */
    private function limpiarFormulario(): void
    {
        $this->reset('cedula', 'nombre', 'ente', 'dependencia', 'piso', 'motivo', 'editandoId');
        $this->nacionalidad = Persona::VENEZOLANO;
        $this->resetValidation();
    }

    /** La plantilla en blanco con las columnas exactas y el ente en desplegable. */
    public function descargarPlantilla(): BinaryFileResponse
    {
        return Excel::download(new PlantillaTrabajadores, 'plantilla-personal.xlsx');
    }

    public function importar(): void
    {
        $this->validate(
            ['archivo' => 'required|file|mimes:xlsx,xls,csv'],
            ['archivo.required' => 'Elige un archivo primero.', 'archivo.mimes' => 'Tiene que ser un Excel (.xlsx, .xls) o un .csv.'],
        );

        $import = new TrabajadoresImport($this->gestion);
        Excel::import($import, $this->archivo->getRealPath());

        $this->reset('archivo');
        $this->erroresDeImportacion = $import->errores;
        app(Auditoria::class)->cargoPersonal('importación · '.$import->guardados.' cargados, '.$import->omitidos.' con error');
        $this->aviso = $import->guardados.' cargados'
            .($import->omitidos > 0 ? ', '.$import->omitidos.' con error' : '').'.';
    }

    public function desactivar(int $id): void
    {
        $persona = Persona::findOrFail($id);
        $this->gestion->desactivar($persona);
        $this->aviso = ($persona->esInvitado() ? 'Invitado' : 'Trabajador').' desactivado: ya no se le puede marcar.';
    }

    public function reactivar(int $id): void
    {
        $persona = Persona::findOrFail($id);
        $this->gestion->reactivar($persona);
        $this->aviso = ($persona->esInvitado() ? 'Invitado' : 'Trabajador').' reactivado.';
    }

    public function render()
    {
        return view('livewire.trabajadores.lista-de-trabajadores');
    }
}
