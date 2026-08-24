<?php

namespace App\Services\Pases;

use App\Models\EntregaDePase;
use App\Models\Movimiento;
use App\Models\Pase;
use App\Models\Persona;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Los pases que están en la calle: quién se llevó cuál y cuáles quedan libres.
 *
 * Un pase es un objeto numerado que se presta y se devuelve, así que esto se parece mucho a las
 * estadías del estacionamiento —y a propósito: es el mismo problema y la misma forma de mirarlo—.
 *
 * Dos reglas sostienen todo lo demás, y las dos son de sentido común en la puerta:
 *
 *   · un pase no puede estar en dos manos a la vez;
 *   · una persona no lleva dos pases encima.
 *
 * Sin ellas el sistema diría que hay pases libres que no lo están, que es peor que no contarlos.
 */
class Pases
{
    /**
     * Los pases que se pueden entregar ahora: habilitados y sin entrega abierta.
     *
     * @return Collection<int, Pase>
     */
    public function libres(): Collection
    {
        $fuera = EntregaDePase::query()->abiertas()->pluck('pase_id')->all();

        return Pase::query()
            ->activos()
            ->when($fuera !== [], fn ($q) => $q->whereNotIn('id', $fuera))
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Los pases que están fuera ahora mismo, el que lleva más tiempo primero.
     *
     * @return Collection<int, EntregaDePase>
     */
    public function fuera(): Collection
    {
        return EntregaDePase::query()
            ->abiertas()
            ->with(['pase', 'persona', 'usuario'])
            ->orderBy('entregado_en')
            ->get();
    }

    /** El pase que lleva esta persona ahora, si lleva alguno. */
    public function deLaPersona(Persona $persona): ?EntregaDePase
    {
        return EntregaDePase::query()
            ->abiertas()
            ->with('pase')
            ->where('persona_id', $persona->id)
            ->latest('entregado_en')
            ->first();
    }

    /**
     * Los visitantes que están dentro y no llevan pase.
     *
     * Es la lista de «ponerse al día»: el día que se cargan los pases ya hay gente dentro a la que
     * nadie le dio ninguno, y buscarlos cédula por cédula no lo hace nadie. También destapa al
     * visitante al que se le olvidó dárselo.
     *
     * Solo invitados: el trabajador entra con su carnet y no lleva pase.
     *
     * @return Collection<int, Persona>
     */
    public function visitantesDentroSinPase(): Collection
    {
        $dentro = Movimiento::ultimoDeCadaPersona()
            ->where('movimientos.tipo', Movimiento::ENTRADA)
            ->pluck('movimientos.persona_id');

        if ($dentro->isEmpty()) {
            return collect();
        }

        $conPase = EntregaDePase::query()->abiertas()->pluck('persona_id')->all();

        return Persona::query()
            ->whereIn('id', $dentro)
            ->where('tipo', Persona::INVITADO)
            ->when($conPase !== [], fn ($q) => $q->whereNotIn('id', $conPase))
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Entrega un pase a alguien.
     *
     * @throws ValidationException
     */
    public function entregar(Pase $pase, Persona $persona, ?int $usuarioId = null): EntregaDePase
    {
        if (! $pase->activo) {
            throw ValidationException::withMessages([
                'pase' => 'Ese pase está deshabilitado: no se puede entregar.',
            ]);
        }

        if ($pase->entregaAbierta() !== null) {
            throw ValidationException::withMessages([
                'pase' => 'El pase '.$pase->codigo.' ya está entregado a otra persona.',
            ]);
        }

        if ($yaTiene = $this->deLaPersona($persona)) {
            throw ValidationException::withMessages([
                'pase' => $persona->nombre.' ya tiene el pase '.$yaTiene->pase?->codigo.'. Hay que recuperarlo antes de darle otro.',
            ]);
        }

        return EntregaDePase::create([
            'pase_id' => $pase->id,
            'persona_id' => $persona->id,
            'entregado_en' => now(),
            'usuario_id' => $usuarioId ?? auth()->id(),
        ]);
    }

    /**
     * Recupera un pase. Devuelve false si ya estaba devuelto —no es un error: dos guardias pueden
     * marcar lo mismo con segundos de diferencia.
     */
    public function devolver(EntregaDePase $entrega, ?int $usuarioId = null): bool
    {
        if ($entrega->devuelto_en !== null) {
            return false;
        }

        $entrega->update([
            'devuelto_en' => now(),
            'devuelto_usuario_id' => $usuarioId ?? auth()->id(),
        ]);

        return true;
    }

    /**
     * Cuántos hay: en la calle, libres y el total habilitado. Para el contador de la pantalla.
     *
     * @return array{fuera:int, libres:int, total:int}
     */
    public function cuentas(): array
    {
        $fuera = EntregaDePase::query()->abiertas()->count();
        $libres = $this->libres()->count();

        return [
            'fuera' => $fuera,
            'libres' => $libres,
            'total' => $fuera + $libres,
        ];
    }

    /**
     * Las entregas de un día: las que se dieron o se devolvieron ese día. Para el histórico.
     *
     * @return Collection<int, EntregaDePase>
     */
    public function delDia(CarbonImmutable $fecha): Collection
    {
        $desde = $fecha->startOfDay();
        $hasta = $fecha->endOfDay();

        return EntregaDePase::query()
            ->with(['pase', 'persona', 'usuario', 'devueltoUsuario'])
            ->where(fn ($q) => $q->whereBetween('entregado_en', [$desde, $hasta])
                ->orWhereBetween('devuelto_en', [$desde, $hasta]))
            ->orderByDesc('entregado_en')
            ->get();
    }
}
