<?php

namespace App\Services\Visitas;

use App\Models\Persona;
use App\Models\VisitaEsperada;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * La agenda de visitas esperadas: agendar, listar el día y marcar la llegada.
 *
 * No decide nada la pantalla; todo pasa por aquí, que valida en el servidor. Es aditivo al marcaje
 * de la puerta: agendar una visita no marca ninguna entrada —eso lo sigue haciendo la parte 1—,
 * solo deja dicho que se la espera. Marcar la llegada aquí es una ayuda de recepción, no el asiento
 * del registro.
 */
class VisitasEsperadas
{
    /**
     * Agenda una visita.
     *
     * @throws ValidationException
     */
    public function agendar(
        string $nombre,
        ?string $cedula = null,
        ?string $aQuienVisita = null,
        ?string $motivo = null,
        ?string $fechaEsperada = null,
        ?string $notas = null,
    ): VisitaEsperada {
        $nombre = trim($nombre);

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Hace falta el nombre de quien viene.']);
        }

        $fecha = $this->fechaValida($fechaEsperada);

        return VisitaEsperada::create([
            'cedula' => $this->cedula($cedula),
            'nombre' => mb_strtoupper($nombre),
            'a_quien_visita' => $this->recorta($aQuienVisita, 120),
            'motivo' => $this->recorta($motivo, 150),
            'fecha_esperada' => $fecha,
            'estado' => VisitaEsperada::ESPERADA,
            'notas' => $this->recorta($notas, 255),
            'registrada_por' => auth()->id(),
        ]);
    }

    /**
     * Las visitas de un día, más recientes primero.
     *
     * @return Collection<int, VisitaEsperada>
     */
    public function delDia(CarbonImmutable $fecha): Collection
    {
        return VisitaEsperada::query()
            ->with('registradaPor')
            ->whereDate('fecha_esperada', $fecha->toDateString())
            ->orderByRaw("case estado when 'esperada' then 0 when 'llego' then 1 else 2 end")
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Las que siguen esperadas de hoy en adelante, para la agenda.
     *
     * @return Collection<int, VisitaEsperada>
     */
    public function proximas(): Collection
    {
        return VisitaEsperada::query()
            ->with('registradaPor')
            ->where('estado', VisitaEsperada::ESPERADA)
            ->whereDate('fecha_esperada', '>=', CarbonImmutable::today()->toDateString())
            ->orderBy('fecha_esperada')
            ->orderBy('nombre')
            ->get();
    }

    public function marcarLlegada(VisitaEsperada $visita): void
    {
        $visita->update(['estado' => VisitaEsperada::LLEGO]);
    }

    public function cancelar(VisitaEsperada $visita): void
    {
        $visita->update(['estado' => VisitaEsperada::CANCELADA]);
    }

    /**
     * ¿Se espera hoy a esta cédula? El puente para que la puerta (parte 1) pueda avisar «a esta
     * persona se la esperaba» al marcarla. Devuelve la primera visita esperada de hoy que coincida.
     */
    public function esperadaHoy(string $cedula): ?VisitaEsperada
    {
        $cedula = $this->cedula($cedula);

        if ($cedula === null) {
            return null;
        }

        return VisitaEsperada::query()
            ->where('cedula', $cedula)
            ->where('estado', VisitaEsperada::ESPERADA)
            ->whereDate('fecha_esperada', CarbonImmutable::today()->toDateString())
            ->first();
    }

    /** La fecha esperada, saneada. Sin fecha, hoy; una fecha ilegible se rechaza. */
    private function fechaValida(?string $fecha): string
    {
        $fecha = trim((string) $fecha);

        if ($fecha === '') {
            return CarbonImmutable::today()->toDateString();
        }

        try {
            return CarbonImmutable::parse($fecha)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['fecha_esperada' => 'Esa fecha no se entiende.']);
        }
    }

    /** Solo dígitos, como en el resto del sistema; vacío queda en null. */
    private function cedula(?string $cedula): ?string
    {
        $cedula = Persona::normalizarCedula((string) $cedula);

        return $cedula === '' ? null : $cedula;
    }

    private function recorta(?string $texto, int $largo): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : mb_substr($texto, 0, $largo);
    }
}
