<?php

namespace App\Livewire\Rostros;

use App\Models\Persona;
use App\Services\Auditoria\Auditoria;
use App\Services\Carnets\PadronDelCarnet;
use App\Services\Rostros\Rostros;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * El índice de rostros: se calcula en el navegador y se guarda aquí.
 *
 * Esta pantalla no reconoce nada. Le da al navegador la lista de quién falta por indexar y la
 * dirección de su foto; el navegador la mira, saca los 128 números y los manda de vuelta. El
 * servidor nunca ve una cara ni tiene ninguna librería de visión.
 *
 * Va con los permisos del personal —«ver-personal» para entrar, «gestionar-personal» para
 * indexar— y no con unos propios: el rostro es un dato más de la ficha, y así esto se puede
 * quitar entero sin dejar permisos huérfanos por el sistema si se decide no usarlo.
 */
class Indice extends Component
{
    /** Lo que se le dice al administrador al terminar. */
    public string $aviso = '';

    /** Cuántas se han indexado en esta pasada, para que la barra avance. */
    public int $hechas = 0;

    /** Las que no se pudieron: sin foto, o con una foto donde no se ve una cara. */
    public array $fallidas = [];

    public function boot(): void
    {
        Gate::authorize('ver-personal');
    }

    /** @return array{indexadas:int, total:int, faltan:int} */
    #[Computed]
    public function estado(): array
    {
        return app(Rostros::class)->estado();
    }

    /**
     * Lo que el navegador tiene que mirar: id, nombre y de dónde sacar la foto.
     *
     * @return array<int, array{id:int, nombre:string, foto:string}>
     */
    #[Computed]
    public function pendientes(): array
    {
        return $this->paraElNavegador(app(Rostros::class)->pendientes());
    }

    /**
     * Todo el personal, para volver a mirarlo entero.
     *
     * Hace falta porque la foto manda y puede cambiar: el índice guarda la cara que TENÍA esa
     * persona el día que se miró, y si en carnets le ponen una foto nueva el índice se queda con
     * la vieja sin que nadie lo note.
     *
     * @return array<int, array{id:int, nombre:string, foto:string}>
     */
    #[Computed]
    public function todos(): array
    {
        return $this->paraElNavegador(app(Rostros::class)->indexables());
    }

    /**
     * A quién le cambió la foto desde que se indexó.
     *
     * NO se calcula al abrir la pantalla: preguntarle al carnets es una llamada por la red, y
     * ponerla en cada render dejaba la pantalla esperando a un sistema que puede no estar. Se pide
     * cuando alguien pulsa «comprobar», que es cuando de verdad interesa.
     *
     * @return array<int, array{id:int, nombre:string, foto:string, hash:?string}>
     */
    public array $desactualizados = [];

    /** Si ya se comprobó en esta pantalla, para saber si decir «ninguno» o no decir nada. */
    public bool $comprobado = false;

    /**
     * La lista que el navegador tiene que mirar, pedida cuando se pulsa.
     *
     * NO viaja en un atributo del HTML. Se probó y estaba mal por dos motivos: el JSON lleva
     * comillas dobles y el atributo también, así que el navegador cortaba el valor por la mitad
     * («missing ) after element list»); y con casi trescientas personas ese atributo pesa más que
     * la página. Pedirlo por Livewire lo arregla de raíz.
     *
     * @return array<int, array{id:int, nombre:string, foto:string, hash:?string}>
     */
    public function listaParaIndexar(string $cual = 'pendientes'): array
    {
        Gate::authorize('gestionar-personal');

        return match ($cual) {
            'todos' => $this->paraElNavegador(app(Rostros::class)->indexables()),
            'desactualizados' => $this->desactualizados,
            default => $this->paraElNavegador(app(Rostros::class)->pendientes()),
        };
    }

    /** Va al carnets, compara los hashes y deja la lista de a quién hay que volver a mirar. */
    public function comprobarCambios(): void
    {
        Gate::authorize('gestionar-personal');

        $this->desactualizados = $this->paraElNavegador(app(Rostros::class)->desactualizados());
        $this->comprobado = true;

        $this->aviso = $this->desactualizados === []
            ? 'Ninguna foto cambió desde la última vez: el índice está al día.'
            : 'A '.count($this->desactualizados).' persona(s) les cambió la foto en carnets.';
    }

    /** Si se puede hablar con la API del carnets: sin eso no hay hashes que comparar. */
    #[Computed]
    public function padronDisponible(): bool
    {
        return app(PadronDelCarnet::class)->configurado();
    }

    /**
     * @param  Collection<int, Persona>  $personas
     * @return array<int, array{id:int, nombre:string, foto:string, hash:?string}>
     */
    private function paraElNavegador($personas): array
    {
        // Los hashes se piden UNA vez para todo el lote: es una llamada al carnets, no una por
        // persona. Vacío si no está configurado, y entonces se indexa igual pero sin poder saber
        // después a quién le cambió la foto.
        $hashes = app(PadronDelCarnet::class)->hashesDeFoto();

        return $personas
            ->map(fn (Persona $persona) => [
                'id' => $persona->id,
                'nombre' => $persona->nombre,
                // Con la hora pegada: si no, el navegador reutiliza la foto que ya tenía guardada
                // y se volvería a indexar la cara vieja, que es justo lo que se quiere evitar.
                'foto' => route('persona.foto', $persona).'?v='.now()->timestamp,
                'hash' => $hashes[(string) $persona->cedula] ?? null,
            ])
            ->all();
    }

    /**
     * Guarda los 128 números que calculó el navegador para una persona.
     *
     * @param  array<int, float>  $descriptor
     */
    public function guardarRostro(int $personaId, array $descriptor): void
    {
        Gate::authorize('gestionar-personal');

        try {
            app(Rostros::class)->guardar(Persona::findOrFail($personaId), $descriptor);
        } catch (ValidationException $e) {
            $this->fallidas[] = ['id' => $personaId, 'motivo' => $e->validator->errors()->first()];

            return;
        }

        $this->hechas++;
    }

    /** El navegador no pudo con esa: sin foto, o sin una cara reconocible en ella. */
    public function noSePudo(int $personaId, string $nombre, string $motivo): void
    {
        Gate::authorize('gestionar-personal');

        $this->fallidas[] = ['id' => $personaId, 'nombre' => $nombre, 'motivo' => $motivo];
    }

    /** El navegador terminó la pasada: se recalcula el estado y se cuenta lo que pasó. */
    public function terminado(): void
    {
        Gate::authorize('gestionar-personal');

        $this->aviso = $this->hechas === 0
            ? 'No se indexó ningún rostro nuevo.'
            : 'Indexados '.$this->hechas.' rostros.';

        if ($this->fallidas !== []) {
            $this->aviso .= ' '.count($this->fallidas).' no se pudieron: sin foto, o sin una cara reconocible en ella.';
        }

        app(Auditoria::class)->anota(Auditoria::INDEXO_ROSTROS, null, $this->aviso);

        $this->reset('desactualizados', 'comprobado');
        unset($this->estado, $this->pendientes, $this->todos);
    }

    /** Vacía el índice entero: la salida si esto se decide no usar. */
    public function vaciar(): void
    {
        Gate::authorize('gestionar-personal');

        $cuantos = app(Rostros::class)->vaciar();

        $this->reset('hechas', 'fallidas');
        $this->aviso = $cuantos === 0 ? 'El índice ya estaba vacío.' : 'Borrados '.$cuantos.' rostros.';

        app(Auditoria::class)->anota(Auditoria::BORRO_ROSTROS, null, $this->aviso);

        $this->reset('desactualizados', 'comprobado');
        unset($this->estado, $this->pendientes, $this->todos);
    }

    public function render()
    {
        return view('livewire.rostros.indice');
    }
}
