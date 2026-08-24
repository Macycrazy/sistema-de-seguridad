<?php

namespace App\Services\Carnets;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * El padrón del personal, tal como lo publica el sistema de carnets.
 *
 * Lo que se busca aquí no son los datos de la gente —esos ya están en «personas»— sino el HASH de
 * la foto de cada quien. Con él, el reconocimiento facial sabe A QUIÉN volver a mirar cuando en
 * carnets le cambian la foto, en vez de reindexar a los sesenta o quedarse con la cara vieja para
 * siempre.
 *
 * Es opcional de arriba abajo: sin token configurado no se llama a nadie y todo lo demás sigue
 * funcionando como antes. Nunca revienta a quien lo llama —si carnets no responde, devuelve vacío
 * y lo deja anotado—, porque un padrón que no llega no puede tumbar una pantalla.
 */
class PadronDelCarnet
{
    /**
     * Corto a propósito, aunque vengan cientos de fichas.
     *
     * Esto lo llama una pantalla mientras alguien la mira: si el carnets no está, más vale decir
     * «no sé» en cinco segundos que dejar la pantalla colgada un cuarto de minuto.
     */
    private const TIMEOUT = 5;

    /**
     * Los hashes ya pedidos en esta petición.
     *
     * El servicio se resuelve una vez por petición, así que memorizarlos aquí evita llamar al
     * carnets varias veces para pintar una sola pantalla —que es lo que pasaba, y se notaba—.
     *
     * @var array<string, string>|null
     */
    private ?array $hashes = null;

    /**
     * El padrón ya pedido en esta petición.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $padron = null;

    /** Si está configurado para hablar con la API. Sin esto, todo lo de aquí queda apagado. */
    public function configurado(): bool
    {
        return trim((string) config('carnets.token')) !== '' && $this->base() !== '';
    }

    /**
     * El hash de la foto de cada cédula: «12345678» => «a1b2c3…».
     *
     * Solo eso, que es lo único que se usa. Devuelve vacío si no está configurado o si carnets no
     * responde: quien llama lo interpreta como «no sé nada», no como «nadie cambió».
     *
     * @return array<string, string>
     */
    public function hashesDeFoto(): array
    {
        if ($this->hashes !== null) {
            return $this->hashes;
        }

        $personal = $this->personal();
        $hashes = [];

        foreach ($personal as $ficha) {
            $cedula = trim((string) ($ficha['cedula'] ?? ''));
            $hash = $ficha['foto']['hash'] ?? null;

            if ($cedula !== '' && is_string($hash) && $hash !== '') {
                $hashes[$cedula] = $hash;
            }
        }

        return $this->hashes = $hashes;
    }

    /**
     * Las cédulas que el carnets tiene fichadas.
     *
     * Sirve para distinguir dos fallos que se ven igual al pedir una foto y no son lo mismo: que
     * esa persona no esté en el carnets, o que esté pero sin foto cargada. La primera se arregla
     * dándola de alta allá; la segunda, subiéndole una foto.
     *
     * Vacío si no está configurado o si el carnets no responde, y entonces no se afirma nada.
     *
     * @return array<int, string>
     */
    public function cedulas(): array
    {
        return array_values(array_filter(array_map(
            fn ($ficha) => trim((string) ($ficha['cedula'] ?? '')),
            $this->personal(),
        ), fn ($cedula) => $cedula !== ''));
    }

    /**
     * El padrón entero, tal cual lo devuelve carnets.
     *
     * @return array<int, array<string, mixed>>
     */
    public function personal(): array
    {
        if ($this->padron !== null) {
            return $this->padron;
        }

        if (! $this->configurado()) {
            return [];
        }

        try {
            $respuesta = Http::timeout(self::TIMEOUT)
                ->withHeaders(['X-API-Token' => trim((string) config('carnets.token'))])
                ->get($this->base().'/api/seguridad/personal');
        } catch (\Throwable $e) {
            Log::warning('No se pudo traer el padrón del carnets: '.$e->getMessage());

            return [];
        }

        if (! $respuesta->successful()) {
            Log::warning('El carnets respondió '.$respuesta->status().' al pedirle el padrón.');

            return [];
        }

        return $this->padron = (array) ($respuesta->json('personal') ?? []);
    }

    /**
     * Comprueba que se puede hablar con la API, para el diagnóstico y el botón de probar.
     *
     * @return array{ok:bool, mensaje:string, total?:int}
     */
    public function probar(): array
    {
        if (! $this->configurado()) {
            return ['ok' => false, 'mensaje' => 'Falta CARNETS_TOKEN (o la dirección del carnets) en el .env.'];
        }

        try {
            $respuesta = Http::timeout(self::TIMEOUT)
                ->withHeaders(['X-API-Token' => trim((string) config('carnets.token'))])
                ->get($this->base().'/api/seguridad/personal');
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo alcanzar el carnets: '.$e->getMessage()];
        }

        return match (true) {
            $respuesta->status() === 401 => ['ok' => false, 'mensaje' => 'El carnets rechazó el token (401). Revisa que sea el mismo de allá y que esta IP esté permitida.'],
            $respuesta->status() === 503 => ['ok' => false, 'mensaje' => 'El carnets dice que no tiene token configurado (503).'],
            ! $respuesta->successful() => ['ok' => false, 'mensaje' => 'El carnets respondió '.$respuesta->status().'.'],
            default => [
                'ok' => true,
                'mensaje' => 'Responde bien.',
                'total' => (int) ($respuesta->json('total') ?? 0),
            ],
        };
    }

    /** La dirección del carnets: la de la API si se puso aparte, y si no la de siempre. */
    private function base(): string
    {
        $base = trim((string) (config('carnets.api') ?: config('carnets.url')));

        return rtrim($base, '/');
    }
}
