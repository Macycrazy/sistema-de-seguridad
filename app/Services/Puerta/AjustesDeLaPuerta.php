<?php

namespace App\Services\Puerta;

use App\Models\Parametro;

/**
 * Qué atajos ofrece la puerta, además de teclear la cédula.
 *
 * Teclear la cédula es lo único que siempre funciona: no depende de que la persona traiga el
 * carnet, ni de la cámara, ni de la luz, ni de estar indexada. Todo lo demás son atajos, y un
 * atajo que no encaja en un puesto —una tableta sin cámara decente, una entrada a contraluz, un
 * carnets que no responde— estorba más que ayuda.
 *
 * Por eso se encienden y se apagan desde el panel, sin desplegar nada: quien está en la puerta
 * necesita poder quitar de en medio lo que le sobra el mismo día que le sobra.
 */
class AjustesDeLaPuerta
{
    private const ESCANER = 'puerta_escaner_qr';

    private const ROSTRO = 'rostros_en_la_puerta';

    /**
     * Si se ofrece escanear el carnet con la cámara.
     *
     * Encendido por omisión: es el atajo probado, el que lleva más tiempo funcionando, y sin él la
     * puerta pierde lo que la hace rápida con quien trae su carnet.
     */
    public function escanerDeCarnet(): bool
    {
        return $this->valor(self::ESCANER, true);
    }

    public function activarEscanerDeCarnet(bool $activo): void
    {
        $this->guardar(self::ESCANER, $activo);
    }

    /**
     * Si se ofrece buscar por la cara.
     *
     * Apagado por omisión, al revés que el escáner: el reconocimiento es lo único de todo el
     * sistema que puede equivocarse diciendo el nombre de OTRA persona, así que se enciende cuando
     * alguien haya decidido que se fía, no por venir puesto de fábrica.
     */
    public function reconocimientoFacial(): bool
    {
        return $this->valor(self::ROSTRO, false);
    }

    public function activarReconocimientoFacial(bool $activo): void
    {
        $this->guardar(self::ROSTRO, $activo);
    }

    private function valor(string $clave, bool $porOmision): bool
    {
        $guardado = Parametro::query()->where('clave', $clave)->value('valor');

        return $guardado === null ? $porOmision : (int) $guardado === 1;
    }

    private function guardar(string $clave, bool $activo): void
    {
        Parametro::updateOrCreate(['clave' => $clave], ['valor' => $activo ? 1 : 0]);
    }
}
