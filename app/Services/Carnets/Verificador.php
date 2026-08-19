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
     * Usa la página pública a la que apunta el propio QR: GET {CARNETS_URL}/Trabajador_{token} con
     * «Accept: application/json». Carnets responde el contrato:
     *   activo   → {"activo":true, "nacionalidad":"V", "cedula":"24270727", "nombre":"...",
     *               "cargo":"...", "gerencia":"..."}
     *   inactivo → {"activo":false}
     *
     * Se llama a la dirección CONFIGURADA (CARNETS_URL), no al host que trae la URL del QR: así la
     * integración sobrevive a que carnets cambie de IP o de dominio. Del QR solo se usa el token.
     *
     * @return array{ok:bool, mensaje?:string, datos?:array<string, mixed>}
     */
    public function verificar(?string $url, string $contenidoQr): array
    {
        $url = $this->base($url);

        if ($url === '') {
            return ['ok' => false, 'mensaje' => 'Falta la dirección del sistema de carnets.'];
        }

        $token = $this->token($contenidoQr);

        if ($token === '') {
            return ['ok' => false, 'mensaje' => 'Ese QR no trae un token de carnet reconocible.'];
        }

        try {
            $respuesta = Http::timeout(self::TIMEOUT)
                ->acceptJson()
                ->get($url.'/Trabajador_'.$token);

            if (! $respuesta->successful()) {
                return ['ok' => false, 'mensaje' => 'Carnets respondió HTTP '.$respuesta->status().'.'];
            }

            return ['ok' => true, 'datos' => (array) $respuesta->json()];
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo consultar: '.$this->motivo($e)];
        }
    }

    /**
     * El token del carnet a partir del contenido del QR.
     *
     * El QR trae una URL «…/Trabajador_{token}»; nos quedamos con lo que va tras «Trabajador_» y lo
     * dejamos en letras y dígitos. Si el QR ya fuese solo el token, también vale.
     */
    public function token(string $contenidoQr): string
    {
        $contenido = trim($contenidoQr);

        if (($pos = mb_strpos($contenido, 'Trabajador_')) !== false) {
            $contenido = mb_substr($contenido, $pos + strlen('Trabajador_'));
        }

        return preg_replace('/[^A-Za-z0-9]/', '', $contenido) ?? '';
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
