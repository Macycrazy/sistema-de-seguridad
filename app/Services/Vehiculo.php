<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * El vehículo en el que llega una persona: si es carro o moto, marca, modelo, color y placa.
 *
 * Existe como clase, y no como cinco cadenas sueltas, porque los mismos datos se limpian, se
 * validan y se guardan en tres sitios distintos —la ficha de la persona, el asiento del
 * movimiento y la pantalla— y así la regla vive en uno solo.
 *
 * Vale igual para un invitado que para un trabajador: el personal también estaciona aquí.
 *
 * Alguien SIN vehículo es lo normal: mucha gente entra caminando. Por eso el objeto vacío es
 * válido y guarda las columnas en nulo. Lo que no se admite es un vehículo a medias sin placa:
 * ver exigirValido().
 */
final class Vehiculo
{
    /** Carro y moto no estacionan en el mismo sitio, y «cuántas motos hay dentro» se pregunta. */
    public const CARRO = 'carro';

    public const MOTO = 'moto';

    /** Los límites de las columnas, para no guardar más de lo que cabe. Ver la migración. */
    public const LARGO_MARCA = 40;

    public const LARGO_MODELO = 40;

    public const LARGO_COLOR = 30;

    public const LARGO_PLACA = 15;

    private function __construct(
        public readonly ?string $tipo,
        public readonly ?string $marca,
        public readonly ?string $modelo,
        public readonly ?string $color,
        public readonly ?string $placa,
    ) {}

    /**
     * Construye el vehículo dejando los datos ya limpios. Lo que llegue vacío queda en nulo,
     * que es como se guarda «no trajo vehículo».
     *
     * El tipo solo tiene sentido si hay vehículo: sin él queda nulo, aunque venga puesto. Y si
     * hay vehículo pero no se eligió tipo, se asume carro, que es lo más común.
     */
    public static function desde(
        ?string $tipo = null,
        ?string $marca = null,
        ?string $modelo = null,
        ?string $color = null,
        ?string $placa = null,
    ): self {
        $vehiculo = new self(
            null,
            self::limpiar($marca, self::LARGO_MARCA),
            self::limpiar($modelo, self::LARGO_MODELO),
            self::limpiar($color, self::LARGO_COLOR),
            self::normalizarPlaca($placa),
        );

        if ($vehiculo->vacio()) {
            return $vehiculo;
        }

        return new self(
            self::normalizarTipo($tipo),
            $vehiculo->marca,
            $vehiculo->modelo,
            $vehiculo->color,
            $vehiculo->placa,
        );
    }

    /** El vehículo tal y como está guardado en una ficha o en un asiento. */
    public static function desdeModelo(object $fila): self
    {
        return self::desde($fila->tipo_vehiculo, $fila->marca, $fila->modelo, $fila->color, $fila->placa);
    }

    /** Solo «carro» o «moto»; cualquier otra cosa se trata como carro. */
    public static function normalizarTipo(?string $tipo): string
    {
        return mb_strtolower(trim((string) $tipo)) === self::MOTO ? self::MOTO : self::CARRO;
    }

    public function esMoto(): bool
    {
        return $this->tipo === self::MOTO;
    }

    /**
     * Deja la placa en solo letras y dígitos, en mayúsculas, para que «AB123CD», «ab-123-cd» y
     * «AB 123 CD» sean la misma placa. Es la misma idea que Persona::normalizarCedula(): si se
     * guarda tal cual se teclea, buscarla luego es una lotería.
     */
    public static function normalizarPlaca(?string $placa): ?string
    {
        $placa = mb_strtoupper(preg_replace('/[^\p{L}\p{N}]/u', '', (string) $placa) ?? '');

        return $placa === '' ? null : mb_substr($placa, 0, self::LARGO_PLACA);
    }

    /**
     * No trajo vehículo. El tipo NO cuenta aquí a propósito: en la pantalla siempre hay uno de
     * los dos botones marcado, así que si contara, nadie podría entrar caminando.
     */
    public function vacio(): bool
    {
        return $this->marca === null
            && $this->modelo === null
            && $this->color === null
            && $this->placa === null;
    }

    /**
     * Un vehículo se admite entero o no se admite: o no hay ninguno, o al menos se sabe la placa.
     *
     * La placa es el único dato que identifica al carro de verdad —marca, modelo y color los
     * comparten miles— así que anotar «Toyota gris» sin placa no sirve para nada el día que haya
     * que averiguar quién dejó ese carro ahí.
     *
     * @throws ValidationException
     */
    public function exigirValido(): void
    {
        if (! $this->vacio() && $this->placa === null) {
            throw ValidationException::withMessages([
                'placa' => 'Si el invitado viene en un vehículo, hace falta la placa.',
            ]);
        }
    }

    /** Las columnas, listas para un create() o un update(). */
    public function paraGuardar(): array
    {
        return [
            'tipo_vehiculo' => $this->tipo,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'color' => $this->color,
            'placa' => $this->placa,
        ];
    }

    /** Cómo se llama: «Carro» o «Moto». Vacío si no trajo ninguno. */
    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            self::MOTO => 'Moto',
            self::CARRO => 'Carro',
            default => '',
        };
    }

    /** Cómo se lee de un vistazo: «Carro · Toyota Corolla · Gris · AB123CD». */
    public function descripcion(): string
    {
        $modelo = trim(($this->marca ?? '').' '.($this->modelo ?? ''));

        return implode(' · ', array_filter([$this->etiquetaTipo(), $modelo, $this->color, $this->placa]));
    }

    /** Recorta y deja un solo espacio entre palabras. Vacío se convierte en nulo. */
    private static function limpiar(?string $texto, int $largo): ?string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', (string) $texto) ?? '');

        return $texto === '' ? null : mb_substr($texto, 0, $largo);
    }
}
