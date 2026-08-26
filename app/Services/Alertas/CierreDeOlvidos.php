<?php

namespace App\Services\Alertas;

use App\Models\AlertaSilenciada;
use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Auditoria\Auditoria;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cierra las entradas que se quedaron abiertas porque nadie marcó la salida.
 *
 * Pasa todos los días: alguien entra, se va por donde sea, y su entrada se queda sin pareja. Eso
 * tiene dos consecuencias y la segunda es la que importa:
 *
 *   · su aviso de permanencia larga no se apaga nunca, y una pantalla con treinta avisos viejos
 *     deja de mirarse —el día que salte uno de verdad, se pierde entre ellos—;
 *   · esa persona sigue contando como «dentro», así que el contador miente y en una emergencia la
 *     lista de quién está en el edificio trae gente que se fue anteayer.
 *
 * Cómo se cierra, y por qué así: la regla del registro es que los movimientos NO se editan ni se
 * borran. No se toca la entrada olvidada; se registra LA SALIDA QUE FALTÓ, marcada como corrección
 * y con quién la hizo. El histórico sigue contando lo que pasó de verdad.
 *
 * La hora real no la sabe nadie, así que se usa la del cierre del edificio de ese día: deja el
 * registro coherente —entró a las 8, salió a las 18— en vez de una salida a las tres de la tarde
 * de dos días después, que se lee como si esa persona hubiera estado ahí todo el tiempo.
 */
class CierreDeOlvidos
{
    /** A qué hora se da por cerrado el edificio cuando nadie marcó la salida. */
    public const HORA_DE_CIERRE = 18;

    /**
     * Cierra el olvido de una persona: registra su salida.
     *
     * Devuelve el movimiento creado, o null si esa persona ya no estaba dentro —dos personas
     * pueden estar cerrando la misma lista a la vez, y eso no es un error.
     */
    public function cerrar(Persona $persona): ?Movimiento
    {
        $ultima = Movimiento::query()
            ->where('persona_id', $persona->id)
            ->orderByDesc('ocurrio_en')
            ->orderByDesc('id')
            ->first();

        if (! $ultima || ! $ultima->esEntrada()) {
            return null;
        }

        $salida = Movimiento::create([
            'persona_id' => $persona->id,
            'tipo' => Movimiento::SALIDA,
            'ocurrio_en' => $this->cuandoCerroElEdificio($ultima->ocurrio_en),
            'usuario_id' => auth()->id(),
            'piso' => $ultima->piso,
            'es_correccion' => true,
        ]);

        app(Auditoria::class)->anota(
            Auditoria::CERRO_OLVIDO,
            (string) $persona->cedula,
            'Entró el '.CarbonImmutable::parse($ultima->ocurrio_en)->format('d/m/Y g:i a')
                .' y nadie le marcó la salida.',
        );

        return $salida;
    }

    /**
     * Cierra varios de una vez. Con treinta y nueve acumulados, de uno en uno no lo hace nadie.
     *
     * @param  iterable<int, Persona>  $personas
     * @return int cuántos se cerraron de verdad
     */
    public function cerrarVarios(iterable $personas): int
    {
        $cuantos = 0;

        foreach ($personas as $persona) {
            $cuantos += $this->cerrar($persona) ? 1 : 0;
        }

        return $cuantos;
    }

    /**
     * Silencia el aviso de alguien que SÍ sigue dentro.
     *
     * El guardia de noche, quien se queda con una avería, un turno de veinte horas. A esos no se
     * les puede marcar una salida que no ocurrió —sería mentir en el registro— pero su aviso
     * tampoco puede quedarse encendido. Se calla hasta mañana; si sigue dentro, vuelve.
     */
    public function silenciar(Persona $persona, ?string $motivo = null): AlertaSilenciada
    {
        $silencio = AlertaSilenciada::silenciar(
            Alerta::PERMANENCIA,
            (string) $persona->id,
            CarbonImmutable::tomorrow()->setTime(self::HORA_DE_CIERRE, 0),
            $motivo,
        );

        app(Auditoria::class)->anota(
            Auditoria::SILENCIO_ALERTA,
            (string) $persona->cedula,
            'Permanencia larga, silenciada hasta mañana'.($motivo ? ': '.$motivo : '.'),
        );

        return $silencio;
    }

    /**
     * Los avisos de permanencia silenciados ahora mismo, por id de persona.
     *
     * @return Collection<int, string>
     */
    public function silenciados(): Collection
    {
        return AlertaSilenciada::query()
            ->vigentes()
            ->where('tipo', Alerta::PERMANENCIA)
            ->pluck('persona_id')
            ->map(fn ($id) => (string) $id);
    }

    /**
     * La hora de cierre del día en que esa persona entró.
     *
     * Si entró DESPUÉS de esa hora —un turno de noche—, se cierra a la hora de cierre del día
     * siguiente: una salida anterior a su entrada no tendría ningún sentido.
     */
    private function cuandoCerroElEdificio($entrada): CarbonImmutable
    {
        $entro = CarbonImmutable::parse($entrada);
        $cierre = $entro->setTime(self::HORA_DE_CIERRE, 0);

        return $cierre->greaterThan($entro) ? $cierre : $cierre->addDay();
    }
}
