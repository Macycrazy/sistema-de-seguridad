<?php

namespace App\Livewire\Trabajadores;

use App\Exports\PlantillaTrabajadores;
use App\Imports\TrabajadoresImport;
use App\Models\Persona;
use App\Services\Auditoria\Auditoria;
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

    /** El formulario de alta empieza cerrado: la pantalla se abre para mirar, no para crear. */
    public bool $creando = false;

    public string $cedula = '';

    public string $nombre = '';

    public string $ente = '';

    public string $dependencia = '';

    public string $piso = '';

    /** El Excel a importar. */
    public $archivo = null;

    public string $busqueda = '';

    /** Lo que se dice después de guardar o importar. */
    public string $aviso = '';

    /** @var array<int, string> Errores por fila de la última importación. */
    public array $erroresDeImportacion = [];

    protected GestionDeTrabajadores $gestion;

    public function boot(): void
    {
        // El permiso en «boot» y no en «mount»: las acciones rehidratan sin volver a montar, así
        // que a quien le quiten el permiso con la pantalla abierta se le corta aquí mismo.
        Gate::authorize('gestionar-personal');

        $this->gestion = app(GestionDeTrabajadores::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function trabajadores(): LengthAwarePaginator
    {
        $aguja = trim($this->busqueda);

        return Persona::query()
            ->where('tipo', Persona::TRABAJADOR)
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
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->paginate(12);
    }

    #[Computed]
    public function entes(): array
    {
        return GestionDeTrabajadores::ENTES;
    }

    public function abrirAlta(): void
    {
        $this->reset('cedula', 'nombre', 'ente', 'dependencia', 'piso', 'aviso');
        $this->resetValidation();
        $this->creando = true;
    }

    public function cancelarAlta(): void
    {
        $this->creando = false;
        $this->reset('cedula', 'nombre', 'ente', 'dependencia', 'piso');
        $this->resetValidation();
    }

    public function guardar(): void
    {
        // Si la validación del servicio falla, la ValidationException sube y Livewire la pinta
        // junto a cada campo. No hace falta atraparla.
        $trabajador = $this->gestion->guardar(
            cedula: $this->cedula,
            nombre: $this->nombre,
            ente: $this->ente,
            dependencia: $this->dependencia,
            piso: $this->piso,
        );

        $this->creando = false;
        $this->reset('cedula', 'nombre', 'ente', 'dependencia', 'piso');
        $this->erroresDeImportacion = [];
        app(Auditoria::class)->cargoPersonal('alta manual · '.$trabajador->cedula);
        $this->aviso = $trabajador->wasRecentlyCreated
            ? 'Trabajador dado de alta.'
            : 'Ese trabajador ya existía; se actualizaron sus datos.';
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
        $this->gestion->desactivar(Persona::where('tipo', Persona::TRABAJADOR)->findOrFail($id));
        $this->aviso = 'Trabajador desactivado: ya no se le puede marcar.';
    }

    public function reactivar(int $id): void
    {
        $this->gestion->reactivar(Persona::where('tipo', Persona::TRABAJADOR)->findOrFail($id));
        $this->aviso = 'Trabajador reactivado.';
    }

    public function render()
    {
        return view('livewire.trabajadores.lista-de-trabajadores');
    }
}
