<?php

namespace App\Models;

use App\Services\DatosVehiculo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * La letra de la cédula. Se guarda aparte del número porque «V-12345678» y «E-12345678» son
     * dos personas distintas: lo único en la tabla es la pareja (nacionalidad, cedula).
     */
    public const VENEZOLANO = 'V';

    public const EXTRANJERO = 'E';

    /** Persona jurídica: una empresa. Su número es un RIF y admite un dígito más. */
    public const JURIDICO = 'J';

    /** Las tres, con el nombre que se le enseña al vigilante en el desplegable. */
    public const NACIONALIDADES = [
        self::VENEZOLANO => 'Venezolano',
        self::EXTRANJERO => 'Extranjero',
        self::JURIDICO => 'Jurídico',
    ];

    /**
     * Los tres entes que comparten el edificio. Los valores coinciden con el enum
     * App\Services\Registro\Ente, que es lo que entiende el filtro del registro.
     */
    public const ENTE_CIIP = 'ciip';

    public const ENTE_MARCA_PAIS = 'marca-pais';

    public const ENTE_VENAPP = 'venapp';

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
        'nacionalidad',
        'tipo',
        'ente',
        'nombre',
        'dependencia',
        // La unidad del organigrama a la que pertenece, cuando está enlazada. Es aditiva sobre
        // «dependencia» (el texto): quien no la tenga se sigue mostrando por su texto. Ver la
        // migración crear_tabla_departamentos.
        'departamento_id',
        // Dónde labora el trabajador, o a dónde se dirige el invitado. Ver la migración.
        'piso',
        'foto_ruta',
        'motivo',
        'activo',
    ];

    /** Cuánto cabe en la columna «piso». Ver la migración. */
    public const LARGO_PISO = 10;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Deja el NÚMERO de la cédula en solo dígitos, para que «12.345.678» y «12345678» sean
     * siempre el mismo. Se usa igual al guardar y al buscar.
     *
     * La letra no sale de aquí: se pregunta aparte y se guarda en «nacionalidad». Si se teclea
     * «V-12.345.678», esta función devuelve «12345678» y la V se pierde —a propósito—, porque
     * quien manda es el desplegable de la pantalla y no lo que venga pegado al número.
     */
    public static function normalizarCedula(?string $cedula): string
    {
        return preg_replace('/\D/', '', (string) $cedula) ?? '';
    }

    /**
     * Deja la letra en una de las tres, en mayúscula.
     *
     * Lo que no reconozca se convierte en «V»: es lo que se venía dando por sentado cuando no se
     * preguntaba, y dejarlo en nulo obligaría a comprobarlo en cada sitio que la use.
     */
    public static function normalizarNacionalidad(?string $nacionalidad): string
    {
        $letra = mb_strtoupper(trim((string) $nacionalidad));

        return isset(self::NACIONALIDADES[$letra]) ? $letra : self::VENEZOLANO;
    }

    /**
     * Deja el piso sin espacios y en mayúsculas, para que «2-1» y «2 - 1» no acaben siendo dos
     * pisos distintos al buscar. Misma idea que la cédula y que la placa.
     *
     * Vacío se convierte en nulo: es como se guarda «no consta».
     */
    public static function normalizarPiso(?string $piso): ?string
    {
        $piso = mb_strtoupper(preg_replace('/\s+/u', '', (string) $piso) ?? '');

        return $piso === '' ? null : mb_substr($piso, 0, self::LARGO_PISO);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    /** La unidad del organigrama a la que pertenece, si está enlazada. */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
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

    /**
     * La última salida registrada, hermana de ultimaEntrada().
     *
     * La pantalla enseña las dos juntas: «entró a las 08:12, salió a las 09:03» dice de un golpe
     * lo que pasó hoy, y con una sola de ellas hay que adivinar la otra.
     */
    public function ultimaSalida(): ?Movimiento
    {
        return $this->movimientos()
            ->where('tipo', Movimiento::SALIDA)
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

    /** La cédula entera, como está en el documento: V-12.345.678 */
    public function cedulaCompleta(): string
    {
        return self::normalizarNacionalidad($this->nacionalidad).'-'.$this->cedulaConPuntos();
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
