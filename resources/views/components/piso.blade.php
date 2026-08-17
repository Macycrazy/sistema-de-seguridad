@props([
    'error' => null,
    // Los pisos que ya se usan en el edificio. Vacío mientras la base esté sin cargar.
    'pisos' => null,
    // Cuál está puesto ahora mismo. Se pasa a mano porque un componente de Blade no ve las
    // propiedades del componente de Livewire que lo usa.
    'piso' => '',
])

@php
    $pisos = $pisos ?? collect();
@endphp

{{--
    A qué piso va. Al invitado se le pregunta SIEMPRE, en cada visita: puede cambiar de una a
    otra, y saber quién hay en cada piso es media razón de ser de este registro.

    Dos formas de rellenarlo, y las dos están a la vez a propósito:

      · LOS ATAJOS — los pisos que ya se usan, sacados de las fichas que hay en la base. Un toque
        y listo, sin teclear. No son una lista fija en el código: cuando se cargue el personal de
        verdad, aparecen solos.

      · LA CASILLA — porque los atajos nunca van a estar completos. Un piso al que todavía no ha
        ido nadie no puede salir en la lista, y aun así tiene que poder anotarse. Por eso la
        casilla no se esconde: los botones la rellenan, no la sustituyen.
--}}
<div>
    @if ($pisos->isNotEmpty())
        <p class="mb-1.5 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
            ¿A qué piso va?
        </p>

        <div class="mb-2 flex flex-wrap gap-2">
            @foreach ($pisos as $p)
                @php $marcado = $piso === $p; @endphp

                {{-- Botón de verdad, no un <label> con radio: el valor no sale de aquí, la casilla
                     sigue mandando. Estos solo la rellenan. --}}
                <button type="button"
                        wire:key="piso-{{ $p }}"
                        wire:click="$set('piso', @js($p))"
                        aria-pressed="{{ $marcado ? 'true' : 'false' }}"
                        class="rounded-full border px-3.5 py-1.5 font-mono text-sm font-semibold tracking-wide
                               transition focus:outline-none focus-visible:ring-4 focus-visible:ring-parte1/25
                               {{ $marcado
                                    ? 'border-parte1 bg-parte1-suave text-parte1'
                                    : 'border-slate-300 text-slate-600 hover:border-slate-400 hover:bg-slate-50' }}">
                    {{ $p }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- La casilla se acomoda sola mientras se teclea —sin espacios y en mayúsculas— igual que la
         placa y por la misma razón: si dejara ver «2 - 1» y en la base quedara «2-1», lo que se ve
         y lo que se guarda no serían lo mismo.

         OJO: ese «oninput» va pegado a los demás atributos. Un comentario de Blade metido entre
         los atributos de un <x-...> rompe el análisis de la etiqueta y se come en silencio lo que
         venga detrás. --}}
    <x-campo
        :etiqueta="$pisos->isNotEmpty() ? 'O escríbelo' : 'Piso'"
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
