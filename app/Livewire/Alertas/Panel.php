<?php

namespace App\Livewire\Alertas;

use App\Models\Persona;
use App\Services\Alertas\Alerta;
use App\Services\Alertas\Alertas;
use App\Services\Alertas\CierreDeOlvidos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La pantalla de alertas: lo que ahora mismo merece que alguien mire.
 *
 * Solo lee. Comparte el permiso «ver-registro» con el registro y los reportes —es la misma
 * información, mirada por lo que se sale de lo normal—, así que no suma permiso ni casilla nueva.
 *
 * No se refresca sola: un «Actualizar» explícito recalcula. Poner un sondeo automático es una
 * decisión de operación (cada cuánto, a costa de qué carga) que no se toma desde el código.
 */
class Panel extends Component
{
    public function boot(): void
    {
        Gate::authorize('ver-registro');
    }

    /** @return Collection<int, Alerta> */
    #[Computed]
    public function alertas(): Collection
    {
        return app(Alertas::class)->activas();
    }

    /** Lo que se le dice a quien acaba de cerrar o silenciar algo. */
    public string $aviso = '';

    /** Cuántas permanencias hay pendientes de cerrar, para poder hacerlo de una vez. */
    #[Computed]
    public function permanencias(): Collection
    {
        return $this->alertas->filter(fn (Alerta $a) => $a->tipo === Alerta::PERMANENCIA)->values();
    }

    /**
     * Registra la salida que faltó: esa persona se fue y nadie la marcó.
     *
     * No se borra nada ni se toca su entrada. Ver CierreDeOlvidos para por qué se hace así y con
     * qué hora.
     */
    public function cerrarOlvido(string $personaId): void
    {
        Gate::authorize('ver-registro');

        $persona = Persona::find($personaId);

        if (! $persona) {
            $this->aviso = 'Esa persona ya no está en el sistema.';

            return;
        }

        $this->aviso = app(CierreDeOlvidos::class)->cerrar($persona)
            ? 'Salida registrada para '.$persona->nombre.'. Su histórico se conserva.'
            : $persona->nombre.' ya no constaba dentro.';

        $this->olvidar();
    }

    /**
     * Cierra todas las permanencias de golpe.
     *
     * Con treinta y nueve acumuladas, de una en una no lo hace nadie, y una pantalla que nadie
     * limpia deja de mirarse.
     */
    public function cerrarTodosLosOlvidos(): void
    {
        Gate::authorize('ver-registro');

        $personas = $this->permanencias
            ->pluck('personaId')
            ->filter()
            ->map(fn ($id) => Persona::find($id))
            ->filter();

        $cuantos = app(CierreDeOlvidos::class)->cerrarVarios($personas);

        $this->aviso = $cuantos === 0
            ? 'No había ninguna que cerrar.'
            : 'Registrada la salida de '.$cuantos.' persona(s). Sus históricos se conservan.';

        $this->olvidar();
    }

    /** Esa persona SÍ sigue dentro: el aviso se calla hasta mañana, sin tocar el registro. */
    public function silenciar(string $personaId): void
    {
        Gate::authorize('ver-registro');

        $persona = Persona::find($personaId);

        if (! $persona) {
            return;
        }

        app(CierreDeOlvidos::class)->silenciar($persona);

        $this->aviso = 'Aviso de '.$persona->nombre.' silenciado hasta mañana. Si sigue dentro, volverá.';
        $this->olvidar();
    }

    private function olvidar(): void
    {
        unset($this->alertas, $this->permanencias);
    }

    public function actualizar(): void
    {
        unset($this->alertas);
    }

    public function render()
    {
        return view('livewire.alertas.panel');
    }
}
