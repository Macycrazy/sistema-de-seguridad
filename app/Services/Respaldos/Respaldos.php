<?php

namespace App\Services\Respaldos;

use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

/**
 * Copias de seguridad de la base: crearlas, listarlas, descargarlas y borrarlas.
 *
 * Un respaldo es un volcado completo de PostgreSQL (pg_dump) guardado en el disco privado, fuera
 * de lo público. Es TODA la data en un archivo, así que solo llega aquí quien tiene
 * «gestionar-respaldos» (el administrador). No se programa solo: lo dispara una persona desde la
 * pantalla, o el comando `respaldo:crear` que un cron puede llamar si Seguridad así lo decide.
 *
 * Las fotos y los archivos generados NO van aquí: esto respalda la base, que es lo que no se puede
 * reconstruir. El respaldo de archivos es cosa del sistema de ficheros del servidor.
 */
class Respaldos
{
    /** Dentro del disco «local» (storage/app/private): nunca en una carpeta servida al público. */
    private const CARPETA = 'respaldos';

    private const DISCO = 'local';

    /**
     * Crea un respaldo y devuelve su nombre y tamaño.
     *
     * @return array{archivo:string, bytes:int}
     *
     * @throws RuntimeException si pg_dump falla
     */
    public function crear(): array
    {
        Storage::disk(self::DISCO)->makeDirectory(self::CARPETA);

        $nombre = 'respaldo-'.now()->format('Ymd-His').'.sql';
        $temporal = tempnam(sys_get_temp_dir(), 'resp');

        try {
            $this->volcar($temporal);

            Storage::disk(self::DISCO)->putFileAs(self::CARPETA, new File($temporal), $nombre);
        } finally {
            @unlink($temporal);
        }

        return [
            'archivo' => $nombre,
            'bytes' => Storage::disk(self::DISCO)->size(self::CARPETA.'/'.$nombre),
        ];
    }

    /**
     * Los respaldos que hay, el más reciente primero.
     *
     * @return Collection<int, array{nombre:string, bytes:int, cuando:Carbon}>
     */
    public function listar(): Collection
    {
        $disco = Storage::disk(self::DISCO);

        return collect($disco->files(self::CARPETA))
            ->filter(fn (string $ruta) => str_ends_with($ruta, '.sql'))
            ->map(fn (string $ruta) => [
                'nombre' => basename($ruta),
                'bytes' => $disco->size($ruta),
                'cuando' => Carbon::createFromTimestamp($disco->lastModified($ruta)),
            ])
            ->sortByDesc('cuando')
            ->values();
    }

    /** La respuesta de descarga de un respaldo. Solo archivos de la carpeta, solo .sql. */
    public function descargar(string $nombre): StreamedResponse
    {
        return Storage::disk(self::DISCO)->download($this->rutaSegura($nombre));
    }

    public function eliminar(string $nombre): void
    {
        Storage::disk(self::DISCO)->delete($this->rutaSegura($nombre));
    }

    public function existe(string $nombre): bool
    {
        return Storage::disk(self::DISCO)->exists($this->rutaSegura($nombre));
    }

    /**
     * Corre pg_dump al archivo destino. Aislado para poder probar la orquestación sin la
     * herramienta: un doble de test lo reemplaza por un volcado de mentira.
     *
     * @throws RuntimeException
     */
    protected function volcar(string $destino): void
    {
        $cnx = config('database.connections.'.config('database.default'));

        $proceso = new Process([
            config('respaldos.pg_dump', 'pg_dump'),
            '--host='.($cnx['host'] ?? '127.0.0.1'),
            '--port='.($cnx['port'] ?? '5432'),
            '--username='.($cnx['username'] ?? 'postgres'),
            '--no-owner',
            '--no-privileges',
            '--format=plain',
            '--file='.$destino,
            $cnx['database'] ?? '',
        ], env: ['PGPASSWORD' => (string) ($cnx['password'] ?? '')]);

        $proceso->setTimeout(600);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            throw new RuntimeException('No se pudo crear el respaldo: '.trim($proceso->getErrorOutput()));
        }
    }

    /** Impide que un «nombre» con «../» o barras salga de la carpeta de respaldos. */
    private function rutaSegura(string $nombre): string
    {
        $nombre = basename($nombre);

        if (! str_ends_with($nombre, '.sql')) {
            throw new RuntimeException('Nombre de respaldo no válido.');
        }

        return self::CARPETA.'/'.$nombre;
    }
}
