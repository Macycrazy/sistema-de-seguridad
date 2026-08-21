<?php

namespace App\Models;

use App\Services\DatosVehiculo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as ConsultaCruda;
use Illuminate\Support\Facades\DB;

/**
 * Una entrada o una salida: el asiento que deja el botón de la puerta.
 *
 * No se edita ni se borra. Un error se corrige registrando un movimiento nuevo.
 * Por eso no lleva «updated_at»: su hora es «ocurrio_en» y no cambia nunca.
 */
class Movimiento extends Model
{
    use HasFactory;

    public const ENTRADA = 'entrada';

    public const SALIDA = 'salida';

    /**
     * Cómo se dice una hora en todo el sistema: «8:12 am», «1:45 pm».
     *
     * Es UNA SOLA definición a propósito. La hora aparece en la etiqueta de quien está dentro, en
     * los últimos movimientos, en los dos avisos de espera y en la confirmación de cada marcaje;
     * escrita a mano en cada sitio, acabaría diciéndose de varias maneras distintas.
     *
     * En 12 horas con am/pm: es como se dice la hora aquí. «Entró a las 2 y cuarto», no «a las
     * catorce quince». El registro y las demás pantallas usan este mismo formato.
     */
    public const FORMATO_HORA = 'g:i a';

    protected $table = 'movimientos';

    /** Sin created_at/updated_at: la hora del asiento es «ocurrio_en». */
    public $timestamps = false;

    /**
     * El ÚLTIMO movimiento de cada persona, como consulta lista para seguir filtrando.
     *
     * Es la base de casi todo lo que pregunta «quién está dentro ahora»: el contador de la puerta,
     * las alertas y el estacionamiento. Los tres lo resolvían por su cuenta, y dos de ellos con un
     * «distinct on» de PostgreSQL —que no existe en SQLite, donde corren las pruebas—. Con esto
     * está escrito una vez, y en un SQL que las dos bases entienden.
     *
     * El último se busca por «max(id)» y no por la hora más alta: el id lo pone la base al
     * insertar, así que no puede haber empate ni depende de que dos asientos del mismo segundo se
     * ordenen bien. Vale porque los movimientos no se editan ni se borran nunca.
     *
     * Quien la use tiene que nombrar la tabla en sus filtros —«movimientos.tipo»—, porque por
     * dentro hay un join y «tipo» a secas sería ambiguo.
     */
    public static function ultimoDeCadaPersona(): ConsultaCruda
    {
        $ultimos = DB::table('movimientos')
            ->selectRaw('persona_id, max(id) as ultimo_id')
            ->groupBy('persona_id');

        return DB::table('movimientos')
            ->joinSub($ultimos, 'u', fn ($union) => $union->on('movimientos.id', '=', 'u.ultimo_id'));
    }

    /**
     * El último movimiento de cada persona HASTA un instante dado: sirve para reconstruir quién
     * estaba dentro en ese momento (por ejemplo, en la medianoche de una noche pasada). Mismo «max(id)»
     * portable, pero mirando solo lo ocurrido hasta esa hora.
     */
    public static function ultimoDeCadaPersonaHasta(CarbonInterface $momento): ConsultaCruda
    {
        $ultimos = DB::table('movimientos')
            ->where('ocurrio_en', '<=', $momento)
            ->selectRaw('persona_id, max(id) as ultimo_id')
            ->groupBy('persona_id');

        return DB::table('movimientos')
            ->joinSub($ultimos, 'u', fn ($union) => $union->on('movimientos.id', '=', 'u.ultimo_id'));
    }

    protected $fillable = [
        'persona_id',
        'tipo',
        'ocurrio_en',
        'usuario_id',
        'motivo',
        // Copia congelada del piso al que fue ese día. Ver docs/esquema.md.
        'piso',
        // Copia congelada del vehículo de ese día. Ver docs/esquema.md.
        'tipo_vehiculo',
        'marca',
        'modelo',
        'color',
        'placa',
        // La plaza a la que se asignó el vehículo al entrar, si se asignó. Opcional.
        'puesto_id',
        // Si al marcar se dijo que iba a pie. Nulo en los asientos de antes de la columna: de
        // aquellos no se sabe. Ver la migración 2026_08_24_120000.
        'a_pie',
    ];

    protected function casts(): array
    {
        return [
            'ocurrio_en' => 'datetime',
            'a_pie' => 'boolean',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** Quién lo registró. Nulo mientras la parte 3 (usuarios) no esté lista. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** La plaza asignada al entrar, si se asignó. */
    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    public function esEntrada(): bool
    {
        return $this->tipo === self::ENTRADA;
    }

    /** El vehículo con el que se registró este asiento, tal y como estaba ese día. */
    public function vehiculo(): DatosVehiculo
    {
        return DatosVehiculo::desdeModelo($this);
    }

    public function tieneVehiculo(): bool
    {
        return ! $this->vehiculo()->vacio();
    }
}
