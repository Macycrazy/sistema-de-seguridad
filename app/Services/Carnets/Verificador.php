<?php

namespace App\Services\Carnets;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * El puente con el sistema de carnets para verificar un QR.
 *
 * La puerta no rehace nada de carnets: le manda el contenido del QR a su API y carnets responde
 * si el carnet es válido, de quién es y si está activo. Con esa cédula, este sistema marca la
 * entrada/salida en su propio registro.
 *
 * La llamada sale del SERVIDOR de seguridad, no del navegador: por eso funciona con la red interna
 * aunque el equipo desde el que se mira esté detrás de una VPN —lo que tiene que alcanzar al
 * carnets es la máquina donde corre este sistema, no la tuya—.
 */
class Verificador
{
    /** Corto a propósito: la puerta no puede quedarse colgada esperando a un carnets que no responde. */
    private const TIMEOUT = 5;

    /**
     * Solo comprueba que el carnets responde en esa dirección. No consulta ningún QR: sirve para
     * el botón de «probar conexión», antes de fiarse de nada.
     *
     * @return array{ok:bool, mensaje:string, ms?:int, http?:int}
     */
    public function probar(?string $url): array
    {
        $url = $this->base($url);

        if ($url === '') {
            return ['ok' => false, 'mensaje' => 'Falta la dirección del sistema de carnets.'];
        }

        try {
            $inicio = microtime(true);
            $respuesta = Http::timeout(self::TIMEOUT)->get($url);
            $ms = (int) round((microtime(true) - $inicio) * 1000);

            return [
                'ok' => true,
                'http' => $respuesta->status(),
                'ms' => $ms,
                'mensaje' => 'El sistema de carnets respondió (HTTP '.$respuesta->status().') en '.$ms.' ms.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No respondió: '.$this->motivo($e)];
        }
    }

    /**
     * Verifica el contenido de un QR contra carnets y devuelve su veredicto.
     *
     * @return array{ok:bool, mensaje?:string, datos?:array<string, mixed>}
     */
    public function verificar(?string $url, string $contenidoQr): array
    {
        $url = $this->base($url);

        if ($url === '') {
            return ['ok' => false, 'mensaje' => 'Falta la dirección del sistema de carnets.'];
        }

        try {
            $respuesta = Http::timeout(self::TIMEOUT)
                ->asForm()
                ->post($url.'/verificar/qr', ['codigo' => $contenidoQr]);

            if (! $respuesta->successful()) {
                // 302/401/403 casi siempre = el endpoint pide sesión de navegador y hay que
                // exponerlo para llamada por token del lado de carnets.
                return [
                    'ok' => false,
                    'mensaje' => 'Carnets respondió HTTP '.$respuesta->status()
                        .'. Si es 302/401/403, el endpoint todavía pide sesión: hay que abrirlo para llamada por token.',
                ];
            }

            return ['ok' => true, 'datos' => (array) $respuesta->json()];
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo consultar: '.$this->motivo($e)];
        }
    }

    /** La URL configurada por omisión (config/carnets.php → CARNETS_URL). */
    public function urlPorOmision(): string
    {
        return $this->base(config('carnets.url'));
    }

    private function base(?string $url): string
    {
        return rtrim(trim((string) $url), '/');
    }

    /** Un motivo corto y legible, sin volcar la excepción entera en pantalla. */
    private function motivo(Throwable $e): string
    {
        $mensaje = trim($e->getMessage());

        return $mensaje === '' ? 'sin respuesta' : mb_substr($mensaje, 0, 200);
    }
}
