<?php

namespace App\Services\Rostros;

use App\Models\Persona;
use App\Models\Rostro;
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
     * La galería para comparar en la puerta: cédula, nombre y descriptor.
     *
     * Va sin foto y sin más datos de los necesarios: lo que viaja al navegador es lo justo para
     * decir «este es Fulano», que es lo único que la puerta necesita.
     *
     * @return array<int, array{cedula:string, nombre:string, descriptor:array<int, float>}>
     */
    public function galeria(): array
    {
        return Rostro::query()
            ->with('persona')
            ->get()
            ->filter(fn (Rostro $rostro) => $rostro->persona?->activo)
            ->map(fn (Rostro $rostro) => [
                'cedula' => (string) $rostro->persona->cedula,
                'nombre' => (string) $rostro->persona->nombre,
                'descriptor' => array_map('floatval', $rostro->descriptor),
            ])
            ->values()
            ->all();
    }

    /**
     * Guarda el descriptor que calculó el navegador. Una persona, un rostro: reindexar lo pisa.
     *
     * @param  array<int, float>  $descriptor
     *
     * @throws ValidationException
     */
    public function guardar(Persona $persona, array $descriptor, string $origen = 'carnet'): Rostro
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

        return Rostro::updateOrCreate(
            ['persona_id' => $persona->id],
            [
                'descriptor' => array_map('floatval', array_values($descriptor)),
                'origen' => $origen,
                'calculado_en' => now(),
            ],
        );
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
        $indexadas = Rostro::query()
            ->whereHas('persona', fn ($q) => $q->where('activo', true)->where('tipo', Persona::TRABAJADOR))
            ->count();

        return [
            'indexadas' => $indexadas,
            'total' => $total,
            'faltan' => max(0, $total - $indexadas),
        ];
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
