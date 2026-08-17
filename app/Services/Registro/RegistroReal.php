<?php

namespace App\Services\Registro;

use App\Models\Movimiento as MovimientoModel;
use App\Models\Persona as PersonaModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * La fuente del registro leyendo de las tablas reales `personas` y `movimientos`.
 *
 * Reemplaza a RegistroInventado ahora que el esquema está acordado y la parte 1 ya escribe
 * movimientos de verdad. Ni el componente Livewire ni las vistas cambian: devuelve los mismos
 * value objects (Registro\Persona y Registro\Movimiento) con la misma semántica.
 *
 * Tres datos del listado real todavía no viven en la tabla `personas`, así que se devuelven
 * en null y sus filtros/columnas quedan en «—» hasta que se agreguen (ver docs/esquema.md):
 *
 *   · ente     — CIIP / Marca País / VENAPP. Sin columna: el filtro por ente no distingue nada.
 *   · cargo    — no se carga todavía.
 *   · el nombre viene en un solo campo, no partido en apellidos y nombres.
 */
final class RegistroReal implements FuenteDelRegistro
{
    public function movimientosDelDia(
        CarbonImmutable $fecha,
        ?TipoDePersona $tipo = null,
        ?Ente $ente = null,
    ): Collection {
        // La tabla `personas` aún no tiene ente: nadie pertenece a uno, así que pedir un ente
        // concreto no puede devolver a nadie. Es honesto —no hay ese dato— hasta que se agregue.
        if ($ente !== null) {
            return collect();
        }

        return MovimientoModel::query()
            ->with(['persona', 'usuario'])
            ->whereDate('ocurrio_en', $fecha->toDateString())
            ->when(
                $tipo,
                fn ($q) => $q->whereHas('persona', fn ($p) => $p->where('tipo', $tipo->value)),
            )
            ->orderByDesc('ocurrio_en')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MovimientoModel $m) => $this->aMovimiento($m));
    }

    public function dentroEn(CarbonImmutable $fecha): int
    {
        // Igual que RegistroInventado: está dentro quien ese día entró y cuyo ÚLTIMO movimiento
        // del día fue una entrada. Acotado al día para que un olvido de salida no se arrastre
        // para siempre. Se ordena ascendente para que last() sea el más reciente.
        return MovimientoModel::query()
            ->whereDate('ocurrio_en', $fecha->toDateString())
            ->orderBy('ocurrio_en')
            ->orderBy('id')
            ->get(['id', 'persona_id', 'tipo', 'ocurrio_en'])
            ->groupBy('persona_id')
            ->filter(fn (Collection $delaPersona) => $delaPersona->last()->tipo === Sentido::Entrada->value)
            ->count();
    }

    public function buscarPersonas(string $texto, int $limite = 8): Collection
    {
        $texto = trim($texto);

        if (mb_strlen($texto) < 2) {
            return collect();
        }

        $aguja = $this->normalizar($texto);

        // La búsqueda se resuelve en PHP, no con ILIKE, para que «perez» encuentre a «Pérez»
        // igual que en la versión inventada: acentos e insensibilidad a mayúsculas por igual.
        // La tabla del personal es acotada y esto nunca devuelve la lista completa al cliente,
        // solo hasta `limite` coincidencias.
        return PersonaModel::query()
            ->get()
            ->map(fn (PersonaModel $p) => $this->aPersona($p))
            ->filter(function (Persona $p) use ($aguja) {
                $documento = $this->normalizar((string) $p->cedula);

                return ($documento !== '' && str_contains($documento, $aguja))
                    || str_contains($this->normalizar($p->nombre()), $aguja);
            })
            ->sortBy(fn (Persona $p) => $p->nombre())
            ->take($limite)
            ->values();
    }

    public function historicoDe(string $personaId): Collection
    {
        return MovimientoModel::query()
            ->with(['persona', 'usuario'])
            ->where('persona_id', $personaId)
            ->orderByDesc('ocurrio_en')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MovimientoModel $m) => $this->aMovimiento($m));
    }

    public function persona(string $personaId): ?Persona
    {
        $persona = PersonaModel::find($personaId);

        return $persona ? $this->aPersona($persona) : null;
    }

    private function aMovimiento(MovimientoModel $movimiento): Movimiento
    {
        return new Movimiento(
            id: (string) $movimiento->id,
            persona: $this->aPersona($movimiento->persona),
            sentido: Sentido::from($movimiento->tipo),
            ocurrioEn: CarbonImmutable::parse($movimiento->ocurrio_en),
            // Quién lo anotó. Nulo mientras haya movimientos de antes del ingreso con usuario.
            registradoPor: $movimiento->usuario?->nombre ?? $movimiento->usuario?->usuario ?? '—',
        );
    }

    private function aPersona(PersonaModel $persona): Persona
    {
        $esInvitado = $persona->tipo === PersonaModel::INVITADO;

        return new Persona(
            id: (string) $persona->id,
            cedula: $this->documentoConPuntos($persona->cedula),
            // La tabla real guarda el nombre en un solo campo. Va entero en `apellidos`, y
            // `nombres` queda vacío: así el nombre se muestra completo y NO se dispara el aviso
            // de «ficha mal cargada» (que salta cuando nombres repite a apellidos), ni el Excel
            // duplica el nombre en sus dos columnas. El día que el listado de personal traiga
            // apellidos y nombres por separado, el corte se hace aquí.
            apellidos: $persona->nombre,
            nombres: '',
            tipo: TipoDePersona::from($persona->tipo),
            ente: null,
            dependencia: $esInvitado ? null : $persona->dependencia,
            piso: $esInvitado ? null : $persona->piso,
            cargo: null,
            // `visitaA` es «a quién visita», dato que la tabla real no guarda: la parte 1 anota
            // el `motivo` de la visita, que es otra cosa. Se deja en null hasta reconciliar los
            // dos (ver la nota de `motivo` en docs/esquema.md).
            visitaA: null,
        );
    }

    /**
     * La cédula con puntos, como en la pantalla de marcar, para que se lea igual en las dos.
     * Solo se le ponen puntos si es numérica; un pasaporte (RD…, FZ…) se deja como viene, y
     * la búsqueda igual lo encuentra porque normaliza antes de comparar.
     */
    private function documentoConPuntos(?string $cedula): ?string
    {
        $cedula = trim((string) $cedula);

        if ($cedula === '') {
            return null;
        }

        return ctype_digit($cedula) ? number_format((int) $cedula, 0, ',', '.') : $cedula;
    }

    /** «perez» encuentra a «Pérez» y «12345678» encuentra a «V-12.345.678». */
    private function normalizar(string $texto): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($texto)));
    }
}
