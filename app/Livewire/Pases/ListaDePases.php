<?php

namespace App\Livewire\Pases;

use App\Models\EntregaDePase;
use App\Models\Pase;
use App\Models\Persona;
use App\Services\Pases\CatalogoDePases;
use App\Services\Pases\Pases;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * El catálogo de pases de visitante y quién lleva cada uno ahora mismo.
 *
 * Dos cosas en una pantalla porque son dos caras del mismo objeto: qué pases hay, y dónde están.
 * La entrega la hace la puerta, en el mismo gesto de marcar al visitante; aquí se cargan, se
 * deshabilitan, y se recupera un pase cuando vuelve sin que nadie marque la salida —que pasa—.
 */
class ListaDePases extends Component
{
    public bool $creando = false;

    public string $codigo = '';

    public string $nota = '';

    /** El alta por tanda: «V-» del 1 al 30, para no teclear treinta pases uno a uno. */
    public bool $creandoTanda = false;

    public string $prefijoTanda = 'V-';

    public string $desdeTanda = '1';

    public string $hastaTanda = '20';

    /** La entrega a mano: a quién y cuál. */
    public bool $entregando = false;

    public string $cedulaEntrega = '';

    public string $paseEntrega = '';

    public string $aviso = '';

    public function boot(): void
    {
        // Para ENTRAR basta con ver; lo que cambia datos exige «gestionar» aparte.
        Gate::authorize('ver-pases');
    }

    /** @return Collection<int, Pase> */
    #[Computed]
    public function pases(): Collection
    {
        return app(CatalogoDePases::class)->todos();
    }

    /** Los que están fuera ahora, por pase, para pintarlo en su fila. */
    #[Computed]
    public function fuera(): Collection
    {
        return app(Pases::class)->fuera()->keyBy('pase_id');
    }

    /** @return array{fuera:int, libres:int, total:int} */
    #[Computed]
    public function cuentas(): array
    {
        return app(Pases::class)->cuentas();
    }

    /**
     * Los pases libres, para el desplegable de la entrega a mano.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function libres(): array
    {
        return app(Pases::class)->libres()
            ->mapWithKeys(fn (Pase $pase) => [(string) $pase->id => $pase->descripcion()])
            ->all();
    }

    /**
     * Entregar un pase desde aquí, sin pasar por la puerta.
     *
     * Hace falta porque el sistema no empieza de cero: cuando se cargan los pases ya hay
     * visitantes dentro, y a ésos nadie les dio ninguno. También sirve para el que llegó mientras
     * no quedaban libres. Lo normal sigue siendo entregarlo al marcar la entrada.
     */
    public function abrirEntrega(): void
    {
        $this->exigirGestionar();
        $this->reset('cedulaEntrega', 'paseEntrega', 'aviso');
        $this->resetValidation();
        $this->entregando = true;
        $this->creando = false;
        $this->creandoTanda = false;
    }

    public function entregar(): void
    {
        $this->exigirGestionar();
        $this->resetValidation();

        $cedula = Persona::normalizarCedula($this->cedulaEntrega);
        $persona = $cedula === '' ? null : Persona::where('cedula', $cedula)->first();

        if (! $persona) {
            $this->addError('cedulaEntrega', 'No hay nadie con esa cédula. Si es alguien nuevo, se le marca primero en la puerta.');

            return;
        }

        if ($this->paseEntrega === '') {
            $this->addError('paseEntrega', 'Elige qué pase se le entrega.');

            return;
        }

        try {
            app(Pases::class)->entregar(Pase::findOrFail((int) $this->paseEntrega), $persona);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->cancelar();
        $this->aviso = 'Pase entregado a '.$persona->nombre.'.';
        $this->olvidar();
    }

    /**
     * Los visitantes que están dentro sin pase: la lista de ponerse al día.
     *
     * @return Collection<int, Persona>
     */
    #[Computed]
    public function sinPase(): Collection
    {
        return app(Pases::class)->visitantesDentroSinPase();
    }

    /** Abre la entrega ya apuntando a esa persona: un toque desde la lista de los que no llevan. */
    public function darPaseA(string $cedula): void
    {
        $this->abrirEntrega();
        $this->cedulaEntrega = $cedula;
    }

    public function abrirNuevo(): void
    {
        $this->exigirGestionar();
        $this->reset('codigo', 'nota', 'aviso');
        $this->resetValidation();
        $this->creando = true;
        $this->creandoTanda = false;
    }

    public function abrirTanda(): void
    {
        $this->exigirGestionar();
        $this->reset('aviso');
        $this->resetValidation();
        $this->creandoTanda = true;
        $this->creando = false;
    }

    public function cancelar(): void
    {
        $this->reset('codigo', 'nota', 'cedulaEntrega', 'paseEntrega');
        $this->resetValidation();
        $this->creando = false;
        $this->creandoTanda = false;
        $this->entregando = false;
    }

    public function guardar(): void
    {
        $this->exigirGestionar();
        $this->resetValidation();

        try {
            $pase = app(CatalogoDePases::class)->guardar($this->codigo, $this->nota);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->cancelar();
        $this->aviso = 'Pase '.$pase->codigo.' guardado.';
        $this->olvidar();
    }

    public function guardarTanda(): void
    {
        $this->exigirGestionar();
        $this->resetValidation();

        try {
            $creados = app(CatalogoDePases::class)->crearTanda(
                $this->prefijoTanda,
                (int) $this->desdeTanda,
                (int) $this->hastaTanda,
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->cancelar();
        $this->aviso = $creados === 0
            ? 'Ninguno nuevo: esos códigos ya estaban en el catálogo.'
            : 'Cargados '.$creados.' pases.';
        $this->olvidar();
    }

    public function habilitar(int $id, bool $activo): void
    {
        $this->exigirGestionar();
        app(CatalogoDePases::class)->habilitar(Pase::findOrFail($id), $activo);
        $this->aviso = $activo ? 'Pase habilitado.' : 'Pase deshabilitado: deja de ofrecerse en la puerta.';
        $this->olvidar();
    }

    public function eliminar(int $id): void
    {
        $this->exigirGestionar();
        $this->resetValidation();

        try {
            app(CatalogoDePases::class)->eliminar(Pase::findOrFail($id));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->aviso = 'Pase quitado del catálogo.';
        $this->olvidar();
    }

    /**
     * Recupera un pase desde aquí.
     *
     * La devolución normal es en la puerta, al marcar la salida del visitante. Esto es para cuando
     * el pase aparece y nadie marcó nada: en el mostrador, en un cajón, al cerrar el turno.
     */
    public function recuperar(int $entregaId): void
    {
        $this->exigirGestionar();

        $entrega = EntregaDePase::findOrFail($entregaId);
        app(Pases::class)->devolver($entrega);

        $this->aviso = 'Pase '.($entrega->pase?->codigo ?? '').' recuperado.';
        $this->olvidar();
    }

    public function render()
    {
        return view('livewire.pases.lista-de-pases');
    }

    private function exigirGestionar(): void
    {
        Gate::authorize('gestionar-pases');
    }

    private function olvidar(): void
    {
        unset($this->pases, $this->fuera, $this->cuentas, $this->libres, $this->sinPase);
    }
}
