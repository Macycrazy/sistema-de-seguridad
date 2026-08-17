@props([
    'error' => null,
    // Las oficinas del edificio agrupadas por piso, con su gerencia:
    //   ['2' => ['2-1' => 'Tecnología', '2-2' => 'Planificación y Presupuesto'], ...]
    'mapa' => [],
    // Qué piso se escogió, y qué oficina. Se pasan a mano porque un componente de Blade no ve
    // las propiedades del componente de Livewire que lo usa.
    'nivel' => '',
    'piso' => '',
    // Si toca escribirlo porque el sitio no está en la lista.
    'aMano' => false,
    // Los pisos que el catálogo nombra: ['9' => 'Presidencia'].
    'nombres' => [],
])

@php
    $mapa = $mapa ?: [];
    $oficinas = $mapa[$nivel] ?? [];

    // Con una sola oficina no se pregunta: al escoger el piso ya quedó anotada. Preguntar para
    // ofrecer una única respuesta es hacer trabajar al vigilante para nada.
    $hayQueElegirOficina = count($oficinas) > 1;

    // La casilla no es una segunda forma de hacer lo que los botones acaban de hacer: solo sale
    // cuando de verdad hace falta —no hay lista, el sitio no consta, o hay que corregir un error—.
    $seEscribe = ! $mapa || $aMano || $error;
@endphp

{{--
    A dónde va el invitado. Se le pregunta SIEMPRE, en cada visita: puede cambiar de una a otra, y
    saber quién hay en cada piso es media razón de ser de este registro.

    Se pregunta en DOS PASOS —primero el piso, después la oficina— y no en una sola lista larga.
    Con cuatro oficinas daría igual; con el edificio entero, una lista de todas obliga a buscar, y
    en la puerta se marca de pie y apurado. El piso lo sabe siempre el que llega («voy al dos»), y
    de ahí salen dos o tres oficinas, no treinta.

    Cada oficina se ofrece CON SU GERENCIA, que es como el visitante dice a dónde va: no pregunta
    por el «2-1», pregunta por Tecnología.

    La lista sale del catálogo del edificio (config/edificio.php) y los nombres, de las fichas del
    personal. Un piso con una sola oficina —el LOBBY, el 7, el 9— queda anotado de un toque.
--}}
<div>
    @if ($mapa)
        <p class="mb-1.5 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
            ¿A qué piso va?
        </p>

        <div class="mb-3 flex flex-wrap gap-2">
            @foreach (array_keys($mapa) as $n)
                @php
                    $nMarcado = ! $aMano && (string) $nivel === (string) $n;

                    // Solo los pisos que el catálogo nombra —hoy el 9, que es Presidencia—. Son
                    // los que no llegan a enseñar la lista de oficinas, así que no tendrían dónde
                    // decir cómo se llaman. Ver Marcar::nombresDePiso().
                    $nombreDelPiso = $nombres[(string) $n] ?? '';
                @endphp

                <button type="button"
                        wire:key="nivel-{{ $n }}"
                        wire:click="elegirNivel(@js((string) $n))"
                        aria-pressed="{{ $nMarcado ? 'true' : 'false' }}"
                        class="min-w-12 whitespace-nowrap rounded-2xl border px-4 py-2 text-center
                               transition focus:outline-none focus-visible:ring-4 focus-visible:ring-parte1/25
                               {{ $nMarcado
                                    ? 'border-parte1 bg-parte1 text-white'
                                    : 'border-slate-300 text-slate-600 hover:border-slate-400 hover:bg-slate-50' }}">
                    <span class="block font-mono text-base font-bold">{{ $n }}</span>

                    @if ($nombreDelPiso)
                        <span class="block text-xs {{ $nMarcado ? 'text-white/80' : 'text-slate-500' }}">
                            {{ $nombreDelPiso }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        @if ($hayQueElegirOficina)
            <p class="mb-1.5 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
                ¿A qué oficina?
            </p>

            <div class="mb-3 flex flex-wrap gap-2">
                @foreach ($oficinas as $codigo => $gerencia)
                    @php $marcada = ! $aMano && $piso === $codigo; @endphp

                    <button type="button"
                            wire:key="oficina-{{ $codigo }}"
                            wire:click="elegirOficina(@js($codigo))"
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

                        {{-- Si nadie labora ahí, debajo no va nada. Poner «sin gerencia anotada»
                             repetiría lo mismo bajo veintitantos botones: sería ruido, y de lo que
                             se trata es de encontrar la oficina de un vistazo. --}}
                        @if ($gerencia)
                            <span class="block text-xs text-slate-500">{{ $gerencia }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif

        {{-- La salida para el sitio que no consta. Va discreta y al final: es la excepción, no el
             camino. --}}
        @unless ($seEscribe)
            <button type="button"
                    wire:click="escribirPisoAMano"
                    class="text-sm text-slate-500 underline underline-offset-2
                           transition hover:text-slate-900
                           focus:outline-none focus-visible:ring-4 focus-visible:ring-parte1/25">
                No aparece en la lista
            </button>
        @endunless
    @endif

    @if ($seEscribe)
        {{-- La casilla se acomoda sola mientras se teclea —sin espacios y en mayúsculas— igual que
             la placa y por la misma razón: si dejara ver «2 - 1» y en la base quedara «2-1», lo que
             se ve y lo que se guarda no serían lo mismo.

             OJO: ese «oninput» va pegado a los demás atributos. Un comentario de Blade metido entre
             los atributos de un <x-...> rompe el análisis de la etiqueta y se come en silencio lo
             que venga detrás. --}}
        <x-campo
            etiqueta="Piso u oficina"
            nombre="piso"
            wire:model.live="piso"
            autocomplete="off"
            placeholder="ej. 2-1"
            class="font-mono"
            maxlength="{{ \App\Models\Persona::LARGO_PISO }}"
            oninput="this.value = this.value.toUpperCase().replace(/\s+/g, '')"
            :error="$error"
        />
    @endif
</div>
