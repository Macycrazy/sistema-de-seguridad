<?php

namespace App\Livewire\Trabajadores;

use App\Exports\PlantillaTrabajadores;
use App\Imports\TrabajadoresImport;
use App\Models\Oficina;
use App\Models\Persona;
use App\Services\Auditoria\Auditoria;
use App\Services\Carnets\CotejoConCarnets;
use App\Services\GestionDeInvitados;
use App\Services\GestionDeTrabajadores;
use App\Services\Registro\Ente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

    /**
     * El cotejo con el padrón del carnets: quién está allá activo y aquí no.
     *
     * Las dos listas se llevan por separado y se separan solas: entra alguien, lo dan de alta en
     * carnets, aquí nadie lo carga, y el día que llega se planta en la puerta y no aparece.
     *
     * Se pide cuando se pulsa y NO al abrir la pantalla: es una llamada por la red a un sistema
     * que puede no estar, y Trabajadores se abre muchas veces al día para otra cosa.
     *
     * @var array<string, mixed>|null
     */
    public ?array $cotejo = null;

    /**
     * Lo que salió mal en la última acción del cotejo, para PODER ENSEÑARLO.
     *
     * Existe porque la pantalla era ciega: las acciones del cotejo avisaban de sus fallos metiendo
     * el mensaje en el saco de errores, y en este panel no se pintaba ninguno —solo el de
     * «archivo», que es de la importación—. Así que un «Cargar» rechazado se veía exactamente
     * igual que un «Cargar» que no hizo nada: la fila seguía ahí y ni un mensaje.
     *
     * Y lo que no era un error de validación —la base de datos, el carnets que deja de responder—
     * ni siquiera llegaba: reventaba la petición entera, y con APP_DEBUG apagado, que es como está
     * en producción, eso en pantalla no se ve de ninguna manera.
     */
    public string $problema = '';

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
        // Para ENTRAR basta con ver; cada acción que cambia datos exige «gestionar» aparte.
        Gate::authorize('ver-personal');

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

    /**
     * Los pisos asociados a la gerencia que hay ahora en el formulario, para ofrecerlos al asignar
     * el piso de un trabajador. Salen del catálogo del edificio (cada oficina tiene su gerencia).
     * Casa por el mismo texto en MAYÚSCULAS; si la gerencia no tiene pisos asociados, no sugiere
     * nada y el piso se escribe a mano como siempre.
     *
     * @return array<int, array{codigo:string, nombre:?string}>
     */
    #[Computed]
    public function pisosDeLaGerencia(): array
    {
        $gerencia = mb_strtoupper(trim($this->dependencia));

        if ($gerencia === '') {
            return [];
        }

        return Oficina::query()
            ->where('gerencia', $gerencia)
            ->orderBy('orden')->orderBy('codigo')
            ->get(['codigo', 'nombre'])
            ->map(fn (Oficina $o) => ['codigo' => $o->codigo, 'nombre' => $o->nombre])
            ->all();
    }

    /** Cambiar el personal es aparte de verlo: quien solo puede ver entra, pero no toca nada. */
    protected function exigirGestion(): void
    {
        Gate::authorize('gestionar-personal');
    }

    public function abrirAlta(): void
    {
        $this->exigirGestion();

        // Alta manual: solo de trabajadores. Los invitados nacen en la puerta, no aquí.
        $this->filtro = Persona::TRABAJADOR;
        $this->limpiarFormulario();
        $this->reset('aviso');
        $this->creando = true;
    }

    /** Carga a una persona en el formulario para corregir sus datos. La cédula queda fija. */
    public function editar(int $id): void
    {
        $this->exigirGestion();

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
        $this->exigirGestion();

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
        $this->exigirGestion();

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
        $this->exigirGestion();

        return Excel::download(new PlantillaTrabajadores, 'plantilla-personal.xlsx');
    }

    public function importar(): void
    {
        $this->exigirGestion();

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
        $this->exigirGestion();

        $persona = Persona::findOrFail($id);
        $this->gestion->desactivar($persona);
        $this->aviso = ($persona->esInvitado() ? 'Invitado' : 'Trabajador').' desactivado: ya no se le puede marcar.';
    }

    public function reactivar(int $id): void
    {
        $this->exigirGestion();

        $persona = Persona::findOrFail($id);
        $this->gestion->reactivar($persona);
        $this->aviso = ($persona->esInvitado() ? 'Invitado' : 'Trabajador').' reactivado.';
    }

    /**
     * Va al carnets y compara las dos listas de personal.
     *
     * Solo cuando se pulsa: es una llamada por la red, y esta pantalla se abre muchas veces al día
     * para buscar a alguien, no para cotejar.
     */
    public function cotejarConCarnets(): void
    {
        Gate::authorize('ver-personal');

        $this->cotejo = app(CotejoConCarnets::class)->comparar();
        $this->aviso = '';
        $this->problema = '';

        if (! $this->cotejo['disponible']) {
            $this->aviso = 'No se pudo consultar el carnets: revisa el token en el .env, o pregúntale a quien lleve el servidor.';

            return;
        }

        $pendientes = $this->cotejo['faltan']->count()
            + $this->cotejo['comoVisitantes']->count()
            + $this->cotejo['desactivados']->count()
            + $this->cotejo['inactivosEnCarnets']->count();

        $this->aviso = $pendientes === 0
            ? 'El estado de aquí coincide con el del carnets.'
            : $pendientes.' diferencia(s) con el carnets. Están abajo, cada una con qué hacer.';
    }

    /**
     * Corre una acción del cotejo dejando dicho en pantalla si no salió.
     *
     * Devuelve si pudo. Las dos formas de fallar acaban igual de visibles: lo que el sistema
     * rechaza a propósito se dice con sus palabras, y lo que se rompe sin avisar se dice como lo
     * que es —sin tragárselo y sin enseñar tripas—, y además queda en el log.
     */
    private function haciendo(string $queHacia, callable $accion): bool
    {
        $this->problema = '';

        try {
            $accion();

            return true;
        } catch (ValidationException $e) {
            $this->problema = $e->validator->errors()->first() ?: 'No se pudo '.$queHacia.'.';
        } catch (\Throwable $e) {
            report($e);

            $this->problema = 'No se pudo '.$queHacia.'. Falló el sistema, no el dato:'
                .' '.class_basename($e).'. Queda anotado en el log.';
        }

        return false;
    }

    /**
     * Da de alta a alguien que ya está en el carnets, con lo que el carnets dice de él.
     *
     * Se hace de uno en uno y pulsando, no de golpe: cargar personal es una decisión, no algo que
     * deba pasar solo porque dos listas no coincidan. La foto se trae sola, como en cualquier alta.
     */
    public function cargarDelPadron(string $cedula): void
    {
        Gate::authorize('gestionar-personal');

        // También se busca entre los que están aquí como visitantes: esos no se cargan —tienen su
        // propio botón—, pero si la petición llega igual conviene decir por qué no, en vez del
        // genérico «ya no está en la lista», que manda a buscar algo que sí está.
        $ficha = collect($this->cotejo['faltan'] ?? [])->firstWhere('cedula', $cedula)
            ?? collect($this->cotejo['comoVisitantes'] ?? [])->firstWhere('cedula', $cedula);

        if (! $ficha) {
            $this->aviso = 'Esa persona ya no está en la lista: vuelve a cotejar.';

            return;
        }

        /*
         * El caso que más se da, y que sin explicar no hay quien lo resuelva: esa cédula YA está
         * aquí, pero como visitante. Pasa con quien vino de visita antes de entrar a trabajar
         * —muchas veces con el nombre mal escrito, como lo tecleó el vigilante—, y luego lo dan de
         * alta en el carnets. El alta se rechaza a propósito: mezclar las dos figuras ensuciaría
         * el registro. Pero decir solo «ya está registrada como visitante» deja a quien mira sin
         * saber dónde está esa ficha ni qué se supone que tiene que hacer.
         */
        $comoVisitante = Persona::where('cedula', Persona::normalizarCedula($ficha['cedula']))
            ->where('tipo', Persona::INVITADO)
            ->first();

        if ($comoVisitante) {
            $this->problema = $ficha['nombre'].' ya está aquí como VISITANTE, con el nombre «'
                .$comoVisitante->nombre.'». No se carga encima: son dos figuras distintas y'
                .' mezclarlas ensuciaría el registro. Resuelve primero esa ficha en la pestaña de'
                .' visitantes.';

            return;
        }

        $pudo = $this->haciendo('cargar a '.$ficha['nombre'], fn () => $this->gestion->guardar(
            cedula: $ficha['cedula'],
            nombre: $ficha['nombre'],
            dependencia: $ficha['gerencia'] ?? null,
        ));

        if (! $pudo) {
            return;
        }

        $this->aviso = $ficha['nombre'].' cargado desde el carnets.';

        // Se rehace el cotejo para que esa persona desaparezca de la lista.
        $this->cotejo = app(CotejoConCarnets::class)->comparar();
    }

    /**
     * Pasa a nómina la ficha de quien aquí era VISITANTE y en el carnets consta de personal.
     *
     * No da de alta nada: cambia la ficha que ya hay. Son la misma persona, y partirla en dos
     * dejaría su histórico repartido entre dos fichas con la misma cédula.
     */
    public function pasarANomina(string $cedula): void
    {
        Gate::authorize('gestionar-personal');

        $ficha = collect($this->cotejo['comoVisitantes'] ?? [])->firstWhere('cedula', $cedula);

        if (! $ficha) {
            $this->aviso = 'Esa persona ya no está en la lista: vuelve a cotejar.';

            return;
        }

        $persona = Persona::where('cedula', Persona::normalizarCedula($cedula))
            ->where('tipo', Persona::INVITADO)
            ->first();

        if (! $persona) {
            $this->aviso = 'Esa ficha de visitante ya no está: vuelve a cotejar.';

            return;
        }

        $pudo = $this->haciendo('pasar a nómina a '.$ficha['nombre'], fn () => $this->gestion->convertirEnTrabajador(
            persona: $persona,
            nombre: $ficha['nombre'],
            ente: Ente::Ciip->value,
            dependencia: $ficha['gerencia'] ?? null,
        ));

        if (! $pudo) {
            return;
        }

        $this->aviso = $ficha['nombre'].' pasó a nómina, con el histórico que ya tenía.';
        app(Auditoria::class)->cargoPersonal('pasó a nómina a '.$ficha['nombre'].' ('.$cedula.'), que estaba como visitante');

        $this->problema = '';
        $this->cotejo = app(CotejoConCarnets::class)->comparar();
    }

    /**
     * Pasa a VISITAS a un trabajador que ya no lo es.
     *
     * Quien se va se desactiva, y su ficha queda sin poder marcar. Pero vuelven —a un trámite, a
     * buscar un papel— y ahí no había por dónde: desactivado no se le marca, y darlo de alta como
     * visita choca con su propia cédula. Esto lo deja entrar como lo que es ahora, sin partir su
     * ficha en dos.
     */
    public function pasarAVisitas(int $id): void
    {
        Gate::authorize('gestionar-personal');

        $persona = Persona::find($id);

        if (! $persona) {
            $this->aviso = 'Esa persona ya no está.';

            return;
        }

        $nombre = $persona->nombre;

        if (! $this->haciendo('pasar a visitas a '.$nombre, fn () => $this->gestion->convertirEnInvitado($persona))) {
            return;
        }

        $this->aviso = $nombre.' pasó a visitas: ya puede marcar como visitante, con su histórico.';
        app(Auditoria::class)->cargoPersonal('pasó a visitas a '.$nombre.' ('.$persona->cedula.')');
    }

    /**
     * Reactiva a alguien que ya está aquí pero desactivado, y en carnets sigue activo.
     *
     * No es lo mismo que cargarlo: su ficha existe, con su histórico y sus datos. Crearla otra vez
     * encima pisaría lo que tenga —el piso, el ente, la dependencia— con lo que diga el carnets.
     */
    public function reactivarDelPadron(string $cedula): void
    {
        Gate::authorize('gestionar-personal');

        $persona = Persona::where('cedula', Persona::normalizarCedula($cedula))->first();

        if (! $persona) {
            $this->aviso = 'Esa persona ya no está: vuelve a comparar.';

            return;
        }

        if (! $this->haciendo('reactivar a '.$persona->nombre, fn () => $this->gestion->reactivar($persona))) {
            return;
        }

        $this->aviso = $persona->nombre.' reactivado. Su histórico se conserva.';

        $this->cotejo = app(CotejoConCarnets::class)->comparar();
    }

    /**
     * Iguala aquí el estado que esa persona tiene en el carnets: la desactiva.
     *
     * De estos no hay duda: en carnets consta su baja. Desactivar conserva su histórico —los
     * movimientos siguen ahí— y se puede deshacer reactivándola, así que es una operación barata.
     */
    public function desactivarComoEnCarnets(string $cedula): void
    {
        Gate::authorize('gestionar-personal');

        // Solo se desactiva a quien el cotejo señala, y el cotejo ya descarta a quien no se puede
        // juzgar: los «No Aplica» y los que pasaron a otro ente del edificio. Sin esta guarda
        // bastaba con mandar una cédula —una pantalla vieja sirve— para desactivar a cualquiera.
        $senalado = collect($this->cotejo['inactivosEnCarnets'] ?? [])
            ->contains(fn ($fila) => (string) (is_array($p = $fila['persona'] ?? null) ? ($p['cedula'] ?? '') : ($p->cedula ?? '')) === (string) Persona::normalizarCedula($cedula));

        if (! $senalado) {
            $this->problema = 'Esa persona no está entre las que el carnets da de baja. Vuelve a comparar.';

            return;
        }

        $persona = Persona::where('cedula', Persona::normalizarCedula($cedula))->first();

        if (! $persona) {
            $this->aviso = 'Esa persona ya no está: vuelve a comparar.';

            return;
        }

        if (! $this->haciendo('desactivar a '.$persona->nombre, fn () => $this->gestion->desactivar($persona))) {
            return;
        }

        $this->aviso = $persona->nombre.' desactivado, como en carnets. Su histórico se conserva.';

        $this->cotejo = app(CotejoConCarnets::class)->comparar();
    }

    /**
     * Iguala el estado de TODOS los que en carnets constan de baja.
     *
     * Se ofrece en bloque solo para esto y no para lo demás: aquí el carnets dice explícitamente
     * que están de baja, y desactivar no borra nada ni impide volver atrás. Cargar o reactivar
     * gente sí crea o cambia fichas, y eso se hace de una en una.
     */
    public function desactivarTodosComoEnCarnets(): void
    {
        Gate::authorize('gestionar-personal');

        $cuantos = 0;

        $pudo = $this->haciendo('desactivarlos', function () use (&$cuantos) {
            foreach ($this->cotejo['inactivosEnCarnets'] ?? [] as $fila) {
                // La ficha puede venir como modelo o —al volver del navegador— como un array
                // pelado: el viaje de ida y vuelta no conserva el objeto. Se acepta de las dos
                // formas, porque leerlo solo de una devolvía null y no desactivaba a nadie sin
                // decir por qué.
                $suya = $fila['persona'] ?? null;
                $id = is_array($suya) ? ($suya['id'] ?? null) : ($suya->id ?? null);
                $persona = $id ? Persona::find($id) : null;

                if ($persona && $persona->activo) {
                    $this->gestion->desactivar($persona);
                    $cuantos++;
                }
            }
        });

        if (! $pudo) {
            return;
        }

        $this->aviso = $cuantos === 0
            ? 'No había ninguno que desactivar.'
            : $cuantos.' persona(s) desactivadas, como en carnets. Su histórico se conserva.';

        $this->cotejo = app(CotejoConCarnets::class)->comparar();
    }

    public function render()
    {
        return view('livewire.trabajadores.lista-de-trabajadores');
    }
}
