<?php

namespace App\Imports;

use App\Models\Persona;
use App\Services\GestionDeTrabajadores;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Carga la nómina desde un Excel.
 *
 * Tolerante con el formato a propósito: no exige una plantilla exacta, sino que busca la fila de
 * encabezados (la que trae «cédula») y mapea las columnas por su nombre. Así sirve tanto para el
 * export del sistema de carnets —que trae una fila de título encima de los encabezados— como para
 * una lista hecha a mano.
 *
 * Cada fila se valida y se guarda con GestionDeTrabajadores, el mismo servicio que usa el alta
 * manual: las reglas son unas solas. Una fila con error no tumba a las demás; se cuenta aparte y
 * se informa con su número, para poder buscarla en el archivo.
 */
class TrabajadoresImport implements ToCollection
{
    public int $guardados = 0;

    public int $omitidos = 0;

    /** @var array<int, string> Errores por número de fila del Excel. */
    public array $errores = [];

    /** Encabezados que sabe reconocer, ya normalizados. */
    private const COLUMNAS = [
        'cedula' => ['cedula', 'ci', 'ncedula', 'ndeci', 'documento', 'cedulaidentidad'],
        'nombre' => ['nombre', 'nombres'],
        'apellido' => ['apellido', 'apellidos'],
        'dependencia' => ['departamento', 'dependencia', 'gerencia', 'dependenciageneral', 'adscripcion'],
        'piso' => ['piso', 'ubicacion'],
        'ente' => ['ente', 'organismo', 'organizacion'],
    ];

    public function __construct(private GestionDeTrabajadores $gestion) {}

    public function collection(Collection $filas): void
    {
        $mapa = null;

        foreach ($filas as $indice => $fila) {
            $celdas = $fila->map(fn ($c) => trim((string) $c))->all();
            $numeroDeFila = $indice + 1;

            // Todavía no se encontró la fila de encabezados: se prueba con ésta.
            if ($mapa === null) {
                $mapa = $this->mapear($celdas);

                continue;
            }

            // Debajo de los encabezados: una fila de datos. Las vacías se saltan en silencio.
            if ($this->vacia($celdas)) {
                continue;
            }

            $this->guardarFila($celdas, $mapa, $numeroDeFila);
        }
    }

    /**
     * ¿Es ésta la fila de encabezados? Lo es si tiene una columna de cédula. Devuelve el mapa
     * «campo => índice de columna», o null si todavía no es.
     *
     * @param  array<int, string>  $celdas
     * @return array<string, int>|null
     */
    private function mapear(array $celdas): ?array
    {
        $mapa = [];

        foreach ($celdas as $columna => $texto) {
            $clave = $this->normalizar($texto);

            foreach (self::COLUMNAS as $campo => $alias) {
                if (in_array($clave, $alias, true)) {
                    $mapa[$campo] ??= $columna;
                }
            }
        }

        // Sin cédula no hay a quién identificar: aún no son los encabezados.
        return isset($mapa['cedula']) ? $mapa : null;
    }

    /**
     * @param  array<int, string>  $celdas
     * @param  array<string, int>  $mapa
     */
    private function guardarFila(array $celdas, array $mapa, int $numeroDeFila): void
    {
        $valor = fn (string $campo) => isset($mapa[$campo]) ? ($celdas[$mapa[$campo]] ?? '') : '';

        $nombre = trim($valor('nombre').' '.$valor('apellido'));
        $ente = $this->enteDesde($valor('ente'));

        try {
            $this->gestion->guardar(
                cedula: $valor('cedula'),
                nombre: $nombre,
                ente: $ente,
                dependencia: $valor('dependencia'),
                piso: $valor('piso'),
            );

            $this->guardados++;
        } catch (ValidationException $e) {
            $this->omitidos++;
            $this->errores[$numeroDeFila] = implode(' ', $e->validator->errors()->all());
        }
    }

    /** El Excel puede traer «CIIP», «Marca País» o «VENAPP»; se traduce a la clave interna. */
    private function enteDesde(string $texto): ?string
    {
        $clave = $this->normalizar($texto);

        return match ($clave) {
            'ciip' => Persona::ENTE_CIIP,
            'marcapais' => Persona::ENTE_MARCA_PAIS,
            'venapp' => Persona::ENTE_VENAPP,
            default => null,
        };
    }

    /** @param  array<int, string>  $celdas */
    private function vacia(array $celdas): bool
    {
        return trim(implode('', $celdas)) === '';
    }

    private function normalizar(string $texto): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($texto)));
    }
}
