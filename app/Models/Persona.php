<?php

namespace App\Models;

use App\Services\DatosVehiculo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Quien puede pasar por la puerta: un trabajador o un invitado.
 *
 * El tipo usa los mismos valores que <x-etiqueta tipo="..."> para que la pantalla y la base
 * de datos hablen igual.
 */
class Persona extends Model
{
    use HasFactory;

    public const TRABAJADOR = 'trabajador';

    public const INVITADO = 'invitado';

    /**
     * Carpeta de las fotos, dentro del disco «local» (storage/app/private).
     *
     * A propósito NO va en storage/app/public ni en public/: ahí cualquiera con la URL vería la
     * cara de un trabajador sin pasar por el sistema. Se sirven por una ruta controlada, que es
     * donde la parte 3 pondrá el permiso y el rastro.
     */
    public const CARPETA_FOTOS = 'fotos';

    protected $table = 'personas';

    protected $fillable = [
        'cedula',
        'tipo',
        'nombre',
        'dependencia',
        'foto_ruta',
        'motivo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Deja la cédula en solo dígitos, para que «12.345.678», «12345678» y «V-12.345.678»
     * sean siempre la misma persona. Se usa igual al guardar y al buscar.
     */
    public static function normalizarCedula(?string $cedula): string
    {
        return preg_replace('/\D/', '', (string) $cedula) ?? '';
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    /** El último movimiento registrado, que es el que dice si está dentro o fuera. */
    public function ultimoMovimiento(): ?Movimiento
    {
        return $this->movimientos()
            ->orderByDesc('ocurrio_en')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * La última entrada registrada, haya salido después o no.
     *
     * No sirve «ultimoMovimiento()» para esto: quien entró y ya salió tiene una salida como
     * último movimiento, y la entrada que interesa está debajo.
     */
    public function ultimaEntrada(): ?Movimiento
    {
        return $this->movimientos()
            ->where('tipo', Movimiento::ENTRADA)
            ->orderByDesc('ocurrio_en')
            ->orderByDesc('id')
            ->first();
    }

    /** Está dentro si su último movimiento fue una entrada. */
    public function estaDentro(): bool
    {
        return $this->ultimoMovimiento()?->tipo === Movimiento::ENTRADA;
    }

    public function esInvitado(): bool
    {
        return $this->tipo === self::INVITADO;
    }

    public function esTrabajador(): bool
    {
        return $this->tipo === self::TRABAJADOR;
    }

    /**
     * Tiene una foto que se puede mostrar de verdad.
     *
     * Se comprueba que el archivo exista, porque un `foto_ruta` apuntando a la nada dejaría un
     * hueco roto en la pantalla. Y se exige que esté dentro de la carpeta de fotos: así, si
     * alguien lograra escribir otra ruta en la base, no serviría para leer un archivo cualquiera
     * del servidor.
     */
    public function tieneFoto(): bool
    {
        return $this->rutaFotoSegura() !== null;
    }

    /** La ruta de la foto solo si es de fiar y el archivo está ahí. Null en cualquier otro caso. */
    public function rutaFotoSegura(): ?string
    {
        $ruta = trim((string) $this->foto_ruta);

        if ($ruta === '' || str_contains($ruta, '..')) {
            return null;
        }

        if (! str_starts_with($ruta, self::CARPETA_FOTOS.'/')) {
            return null;
        }

        return Storage::disk('local')->exists($ruta) ? $ruta : null;
    }

    /**
     * Iniciales para el hueco de la foto. El servidor no tiene salida a Internet, así que no
     * se piden avatares a ningún servicio: se dibujan con las letras del nombre.
     */
    public function iniciales(): string
    {
        $palabras = preg_split('/\s+/', trim($this->nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(
            mb_substr($palabras[0] ?? '', 0, 1).mb_substr($palabras[1] ?? '', 0, 1)
        );
    }

    /** La cédula como se lee en voz alta: 12.345.678 */
    public function cedulaConPuntos(): string
    {
        return number_format((int) $this->cedula, 0, ',', '.');
    }

    /**
     * Los vehículos que tiene anotados. Pueden ser varios: carro y moto, por ejemplo.
     *
     * Se ordenan por placa para que la lista de la puerta salga siempre igual. Si cambiara de
     * orden entre una visita y otra, el vigilante acabaría marcando el equivocado.
     */
    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class)->orderBy('placa');
    }

    public function tieneVehiculos(): bool
    {
        return $this->vehiculos()->exists();
    }

    /** El vehículo de esta persona con esa placa, si lo tiene. */
    public function vehiculoConPlaca(?string $placa): ?Vehiculo
    {
        $placa = DatosVehiculo::normalizarPlaca($placa);

        return $placa ? $this->vehiculos()->where('placa', $placa)->first() : null;
    }

    /**
     * En qué llegó la última vez que entró, si en algo.
     *
     * Se saca del asiento y no de la ficha: el asiento dice lo que trajo ESE día. Sirve para que
     * la pantalla proponga lo mismo cuando vuelve, que casi siempre acierta.
     */
    public function placaDeLaUltimaEntrada(): ?string
    {
        return $this->ultimaEntrada()?->placa;
    }
}
