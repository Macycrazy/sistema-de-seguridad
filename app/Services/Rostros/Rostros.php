<?php

namespace App\Services\Rostros;

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
     * Cuántas muestras se guardan como máximo por persona.
     *
     * Cada una es otra oportunidad de reconocerla, pero también más trabajo por cuadro de vídeo y
     * más peso al descargar la galería. Seis cubre de sobra la variedad de una misma cara —luz,
     * gafas, barba— sin que la puerta se note más lenta.
     */
    public const MAX_MUESTRAS = 6;

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
                    ->map(fn (Rostro $rostro) => array_map('floatval', $rostro->descriptor))
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
            ->slice(self::MAX_MUESTRAS);

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
