@props([
    'error' => null,
    // Las oficinas del edificio agrupadas por piso, con su gerencia:
    //   ['2' => ['2-1' => 'Tecnología', '2-2' => 'Planificación y Presupuesto'], ...]
    'mapa' => [],
    // Qué piso se escogió, y qué oficina. Se pasan a mano porque un componente de Blade no ve
    // las propiedades del componente de Livewire que lo usa.
    'nivel' => '',
    'piso' => '',
])

@php
    $mapa = $mapa ?: [];
    $oficinas = $mapa[$nivel] ?? [];
@endphp

{{--
    A dónde va el invitado. Se le pregunta SIEMPRE, en cada visita: puede cambiar de una a otra, y
    saber quién hay en cada piso es media razón de ser de este registro.

    Se pregunta en DOS PASOS —primero el piso, después la oficina— y no en una sola lista larga.
    Con cuatro oficinas daría igual; con un edificio entero, una lista de todas obliga a buscar, y
    en la puerta se marca de pie y apurado. El piso lo sabe siempre el que llega («voy al dos»), y
    de ahí salen dos o tres oficinas, no treinta.

    Cada oficina se ofrece CON SU GERENCIA, que es como el visitante dice a dónde va: no pregunta
    por el «2-1», pregunta por Tecnología.

    La lista no está escrita en el código: sale de las fichas del personal, donde el piso y la
    gerencia ya conviven. Se mantiene sola.

    Y la casilla de escribir no se esconde nunca: una oficina donde todavía no labora nadie no
    puede estar en la lista, y aun así tiene que poder anotarse. Los botones la rellenan.
--}}
<div>
    @if ($mapa)
        <p class="mb-1.5 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
            ¿A qué piso va?
        </p>

        <div class="mb-3 flex flex-wrap gap-2">
            @foreach (array_keys($mapa) as $n)
                @php $nMarcado = (string) $nivel === (string) $n; @endphp

                <button type="button"
                        wire:key="nivel-{{ $n }}"
                        wire:click="elegirNivel(@js((string) $n))"
                        aria-pressed="{{ $nMarcado ? 'true' : 'false' }}"
                        class="min-w-12 whitespace-nowrap rounded-full border px-4 py-2 font-mono text-base font-bold
                               transition focus:outline-none focus-visible:ring-4 focus-visible:ring-parte1/25
                               {{ $nMarcado
                                    ? 'border-parte1 bg-parte1 text-white'
                                    : 'border-slate-300 text-slate-600 hover:border-slate-400 hover:bg-slate-50' }}">
                    {{ $n }}
                </button>
            @endforeach
        </div>

        @if ($oficinas)
            <p class="mb-1.5 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
                ¿A qué oficina?
            </p>

            <div class="mb-3 flex flex-wrap gap-2">
                @foreach ($oficinas as $codigo => $gerencia)
                    @php $marcada = $piso === $codigo; @endphp

                    <button type="button"
                            wire:key="oficina-{{ $codigo }}"
                            wire:click="$set('piso', @js($codigo))"
                            aria-pressed="{{ $marcada ? 'true' : 'false' }}"
                            class="whitespace-nowrap rounded-2xl border px-4 py-2 text-left
                                   transition focus:outline-none focus-visible:ring-4 focus-visible:ring-parte1/25
                                   {{ $marcada
                                        ? 'border-parte1 bg-parte1-suave'
                                        : 'border-slate-300 hover:border-slate-400 hover:bg-slate-50' }}">
                        <span class="block font-mono text-sm font-bold tracking-wide
                                     {{ $marcada ? 'text-parte1' : 'text-slate-700' }}">
                            {{ $codigo }}
                        </span>
                        <span class="block text-xs text-slate-500">
                            {{ $gerencia ?: 'Sin gerencia anotada' }}
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    @endif

    {{-- La casilla se acomoda sola mientras se teclea —sin espacios y en mayúsculas— igual que la
         placa y por la misma razón: si dejara ver «2 - 1» y en la base quedara «2-1», lo que se ve
         y lo que se guarda no serían lo mismo.

         OJO: ese «oninput» va pegado a los demás atributos. Un comentario de Blade metido entre
         los atributos de un <x-...> rompe el análisis de la etiqueta y se come en silencio lo que
         venga detrás. --}}
    <x-campo
        :etiqueta="$mapa ? 'O escribe el código' : 'Piso'"
        nombre="piso"
        wire:model.live="piso"
        autocomplete="off"
        placeholder="ej. 2-1"
        class="font-mono"
        maxlength="{{ \App\Models\Persona::LARGO_PISO }}"
        oninput="this.value = this.value.toUpperCase().replace(/\s+/g, '')"
        :error="$error"
    />
</div>
