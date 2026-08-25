<?php

namespace App\Services\Rostros;

use App\Models\Parametro;
use App\Models\Persona;
use App\Models\Rostro;
use App\Services\Carnets\PadronDelCarnet;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El índice de rostros: quién está indexado, quién falta, y guardar lo que calcula el navegador.
 *
 * El cálculo NO pasa por aquí. Lo hace el navegador —la foto no sale del equipo— y este servicio
 * solo recibe los 128 números y los guarda. Por eso no hay ninguna librería de visión en el
 * servidor: el reconocimiento vive entero en el cliente.
 *
 * La comparación tampoco: la puerta se descarga la galería (128 números por persona, unos kilos)
 * y compara ahí mismo. El servidor nunca ve una cara.
 */
class Rostros
{
    /**
     * Las personas que se pueden indexar: personal activo, que es de quien hay foto en carnets.
     *
     * A los visitantes no se les indexa: su foto no está en ninguna parte, y guardarles la cara
     * sería recoger biometría de quien solo viene de visita.
     *
     * @return Collection<int, Persona>
     */
    public function indexables(): Collection
    {
        return Persona::query()
            ->where('activo', true)
            ->where('tipo', Persona::TRABAJADOR)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Cuántas muestras por persona si nadie lo cambia.
     *
     * Seis no es una ley, es un punto de partida. Lo que de verdad limita son dos cosas:
     *
     *   · EL PESO. La galería se descarga entera al abrir la cámara. Con 296 personas, cada
     *     muestra más son unos 250 kB; a seis son 1,5 MB, a doce 3 MB. En red local no se nota, en
     *     un wifi flojo sí.
     *   · LOS FALSOS POSITIVOS. Al comparar se toma la MEJOR de las muestras de cada quien, y
     *     cuantas más haya, más fácil es que alguna caiga cerca por casualidad. Muchas muestras
     *     reconocen mejor a la persona y también se parecen más a los demás; pasado cierto punto
     *     habría que exigir más para compensar.
     *
     * La ganancia, en cambio, no crece igual: de una a tres o cuatro está casi toda: es donde
     * entran la otra luz, las gafas y el otro peinado. De ahí para arriba, más de lo mismo.
     *
     * Se puede subir desde la pantalla; ver «maxMuestras».
     */
    public const MAX_MUESTRAS_POR_OMISION = 6;

    /** Hasta dónde se deja subir. Por encima, el peso y los falsos positivos ganan a la ganancia. */
    public const TOPE_MUESTRAS = 20;

    /**
     * Lo estricto que se pone la puerta al decir un nombre.
     *
     * Los tres se ajustan desde la pantalla porque el punto bueno depende de las fotos que haya y
     * de cuánta gente. Lo que NO se ajusta es la idea: es mejor no reconocer que reconocer mal.
     * Un «no lo reconozco» obliga a usar el carnet; un nombre equivocado mete a otra persona en el
     * registro y nadie lo revisa.
     *
     *   · umbral   — a qué distancia como máximo se considera la misma persona. Más bajo, más
     *                estricto. Con casi trescientas caras conviene ser estricto: algo a media
     *                distancia se parece a demasiada gente.
     *   · margen   — cuánto más lejos tiene que estar el SEGUNDO candidato. Sin esto, dos personas
     *                parecidas a 0,44 y 0,46 se resuelven a cara o cruz.
     *   · confirma — cuántos cuadros seguidos tiene que ganar el mismo. Un cuadro malo acierta por
     *                casualidad; dos seguidos con la misma persona, ya no.
     *
     * @return array{umbral:float, margen:float, confirmaciones:int}
     */
    public function ajustes(): array
    {
        return [
            'umbral' => $this->deMilesimas('rostros_umbral', 0.45),
            'margen' => $this->deMilesimas('rostros_margen', 0.06),
            'confirmaciones' => (int) ($this->parametro('rostros_confirmaciones') ?? 2),
        ];
    }

    /** Guarda lo estricto que se pone la puerta. Los rangos evitan dejarla inservible o crédula. */
    public function fijarAjustes(float $umbral, float $margen, int $confirmaciones): void
    {
        $this->aMilesimas('rostros_umbral', max(0.30, min(0.70, $umbral)));
        $this->aMilesimas('rostros_margen', max(0.0, min(0.30, $margen)));
        $this->guardarParametro('rostros_confirmaciones', max(1, min(5, $confirmaciones)));
    }

    /**
     * Las distancias son decimales y la tabla «parametros» guarda enteros —la comparten los
     * umbrales de alerta y las reglas de tiempo, que se cuentan en horas y en personas—. Se
     * guardan en milésimas: 0,45 se escribe 450.
     *
     * Es preferible a añadirle una columna de texto a una tabla que usan otros tres módulos por
     * un decimal que aquí nunca pasa de tres cifras.
     */
    private function deMilesimas(string $clave, float $porOmision): float
    {
        $valor = $this->parametro($clave);

        return $valor === null ? $porOmision : round(((int) $valor) / 1000, 3);
    }

    private function aMilesimas(string $clave, float $valor): void
    {
        $this->guardarParametro($clave, (int) round($valor * 1000));
    }

    private function parametro(string $clave): ?string
    {
        $valor = Parametro::query()->where('clave', $clave)->value('valor');

        return $valor === null ? null : (string) $valor;
    }

    private function guardarParametro(string $clave, int $valor): void
    {
        Parametro::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }

    /** Cuántas muestras por persona se guardan, según lo configurado. */
    public function maxMuestras(): int
    {
        $valor = (int) (Parametro::query()->where('clave', 'rostros_max_muestras')->value('valor')
            ?? self::MAX_MUESTRAS_POR_OMISION);

        return max(1, min(self::TOPE_MUESTRAS, $valor));
    }

    /** Cambia cuántas muestras se guardan por persona. */
    public function fijarMaxMuestras(int $cuantas): void
    {
        Parametro::updateOrCreate(
            ['clave' => 'rostros_max_muestras'],
            ['valor' => max(1, min(self::TOPE_MUESTRAS, $cuantas))],
        );
    }

    /**
     * La galería para comparar en la puerta: cédula, nombre y TODAS sus muestras.
     *
     * Va sin foto y sin más datos de los necesarios: lo que viaja al navegador es lo justo para
     * decir «este es Fulano», que es lo único que la puerta necesita.
     *
     * Las muestras van juntas por persona porque al comparar se toma la MEJOR de las suyas. No se
     * promedian: la media entre la cara de 2019 y la de hoy es una cara que no existe.
     *
     * @return array<int, array{cedula:string, nombre:string, descriptores:array<int, array<int, float>>}>
     */
    public function galeria(): array
    {
        return Rostro::query()
            ->with('persona')
            ->get()
            ->filter(fn (Rostro $rostro) => $rostro->persona?->activo)
            ->groupBy(fn (Rostro $rostro) => (string) $rostro->persona->cedula)
            ->map(fn ($muestras, $cedula) => [
                'cedula' => (string) $cedula,
                'nombre' => (string) $muestras->first()->persona->nombre,
                'descriptores' => $muestras
                    // Redondeados a cuatro decimales: la galería viaja entera al navegador y así
                    // pesa un tercio. Las distancias entre caras se juegan en el segundo y el
                    // tercer decimal, así que el cuarto no cambia ninguna decisión.
                    ->map(fn (Rostro $rostro) => array_map(fn ($n) => round((float) $n, 4), $rostro->descriptor))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Las muestras de una persona, de la más reciente a la más antigua.
     *
     * @return Collection<int, Rostro>
     */
    public function muestrasDe(Persona $persona): Collection
    {
        return Rostro::query()
            ->where('persona_id', $persona->id)
            ->orderByDesc('calculado_en')
            ->orderByDesc('id')
            ->get();
    }

    /** Quita una muestra concreta. Las demás de esa persona se quedan. */
    public function olvidarMuestra(Rostro $rostro): void
    {
        $rostro->delete();
    }

    /**
     * Guarda el descriptor que calculó el navegador. Una persona, un rostro: reindexar lo pisa.
     *
     * @param  array<int, float>  $descriptor
     *
     * @throws ValidationException
     */
    public function guardar(Persona $persona, array $descriptor, string $origen = 'carnet', ?string $hashFoto = null): Rostro
    {
        if (count($descriptor) !== Rostro::LARGO) {
            throw ValidationException::withMessages([
                'rostro' => 'Ese descriptor no tiene la forma que debería: llegaron '.count($descriptor).' números en vez de '.Rostro::LARGO.'.',
            ]);
        }

        foreach ($descriptor as $numero) {
            if (! is_numeric($numero)) {
                throw ValidationException::withMessages(['rostro' => 'El descriptor trae algo que no es un número.']);
            }
        }

        $atributos = [
            'descriptor' => array_map('floatval', array_values($descriptor)),
            'origen' => $origen,
            'hash_foto' => $hashFoto,
            'calculado_en' => now(),
        ];

        // La del carnet es UNA: reindexar la sustituye, que es de lo que va reindexar. Las demás se
        // acumulan, porque cada una es una cara distinta de la misma persona.
        if ($origen === Rostro::DEL_CARNET) {
            return Rostro::updateOrCreate(
                ['persona_id' => $persona->id, 'origen' => Rostro::DEL_CARNET],
                $atributos,
            );
        }

        $nueva = Rostro::create($atributos + ['persona_id' => $persona->id]);

        $this->recortarMuestras($persona);

        return $nueva;
    }

    /**
     * Deja a esa persona en el máximo de muestras, tirando las más viejas.
     *
     * La del carnet NO se cuenta ni se tira: es la de referencia, la única que se puede volver a
     * calcular sola, y si se perdiera habría que reindexar para recuperarla.
     */
    private function recortarMuestras(Persona $persona): void
    {
        $sobran = Rostro::query()
            ->where('persona_id', $persona->id)
            ->where('origen', '!=', Rostro::DEL_CARNET)
            ->orderByDesc('calculado_en')
            ->orderByDesc('id')
            ->get()
            ->slice($this->maxMuestras());

        foreach ($sobran as $vieja) {
            $vieja->delete();
        }
    }

    /** Quita el rostro de una persona. */
    public function olvidar(Persona $persona): void
    {
        Rostro::where('persona_id', $persona->id)->delete();
    }

    /** Vacía el índice entero. Es la salida de emergencia si esto se decide no usar. */
    public function vaciar(): int
    {
        $cuantos = Rostro::count();
        Rostro::query()->delete();

        return $cuantos;
    }

    /**
     * Cómo va el índice: cuántos hay, cuántos faltan y de cuántos no hay ni foto.
     *
     * @return array{indexadas:int, total:int, faltan:int}
     */
    public function estado(): array
    {
        $total = $this->indexables()->count();

        // PERSONAS con al menos una muestra, no muestras: ahora puede haber varias por cabeza.
        $indexadas = Rostro::query()
            ->whereHas('persona', fn ($q) => $q->where('activo', true)->where('tipo', Persona::TRABAJADOR))
            ->distinct('persona_id')
            ->count('persona_id');

        return [
            'indexadas' => $indexadas,
            'total' => $total,
            'faltan' => max(0, $total - $indexadas),
        ];
    }

    /**
     * A quién le cambió la foto desde que se le indexó: los que hay que volver a mirar.
     *
     * Se pregunta a carnets por el hash de cada foto y se compara con el que se guardó. Los que no
     * tienen hash guardado —de antes de que existiera la columna— NO cuentan: de ellos no se sabe
     * con qué foto se hicieron, y darlos por viejos sería reindexar a todos, que es justo lo que
     * esto viene a evitar.
     *
     * Si carnets no responde devuelve vacío, que se lee como «no sé de nadie» y no como «nadie
     * cambió»: la pantalla lo dice en vez de callar.
     *
     * @return Collection<int, Persona>
     */
    public function desactualizados(): Collection
    {
        $hashes = app(PadronDelCarnet::class)->hashesDeFoto();

        if ($hashes === []) {
            return collect();
        }

        $rostros = Rostro::query()->with('persona')->whereNotNull('hash_foto')->get();

        return $rostros
            ->filter(function (Rostro $rostro) use ($hashes) {
                $persona = $rostro->persona;

                if (! $persona || ! $persona->activo) {
                    return false;
                }

                $ahora = $hashes[(string) $persona->cedula] ?? null;

                // Sin hash en el padrón, esa foto no se puede comprobar: no se toca.
                return $ahora !== null && $ahora !== $rostro->hash_foto;
            })
            ->map(fn (Rostro $rostro) => $rostro->persona)
            ->values();
    }

    /**
     * Las personas que aún no tienen rostro indexado: lo que le queda por hacer al navegador.
     *
     * @return Collection<int, Persona>
     */
    public function pendientes(): Collection
    {
        $yaEstan = Rostro::query()->pluck('persona_id')->all();

        return $this->indexables()
            ->when($yaEstan !== [], fn (Collection $c) => $c->reject(fn (Persona $p) => in_array($p->id, $yaEstan, true)))
            ->values();
    }
}
