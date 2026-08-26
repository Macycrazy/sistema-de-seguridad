{{--
    Qué se le ofrece al vigilante en la pantalla de marcar.

    Los tres juntos porque la pregunta siempre es la misma; repartidos por sus pantallas obligaban
    a recordar dónde estaba cada uno.
--}}
<div>
    @if ($aviso !== '')
        <x-aviso class="mb-4" wire:key="aviso-atajos">{{ $aviso }}</x-aviso>
    @endif

    <div class="space-y-3">
        @php
            $atajos = [
                ['cual' => 'cedula', 'puesto' => $cedula,
                 'titulo' => 'Teclear la cédula',
                 'texto' => 'Lo único que no depende de nada: ni del carnet, ni de la cámara, ni de la luz. Va primero en la pantalla.'],

                ['cual' => 'escaner', 'puesto' => $escaner,
                 'titulo' => 'Escanear el carnet con la cámara',
                 'texto' => 'Más rápido que teclear cuando lo traen. Apágalo si en ese puesto la cámara no da o el sitio está a contraluz.'],

                ['cual' => 'rostro', 'puesto' => $rostro,
                 'titulo' => 'Buscar por la cara',
                 'texto' => 'Para quien llega sin carnet. Propone quién es y el vigilante confirma; necesita caras indexadas en Reconocimiento facial.'],
            ];
        @endphp

        @foreach ($atajos as $atajo)
            <label class="flex cursor-pointer items-center gap-4 rounded border px-4 py-3 transition
                          {{ $atajo['puesto'] ? 'border-parte1 bg-parte1-suave' : 'border-slate-200 bg-white' }}">
                <input type="checkbox" wire:model.live="{{ $atajo['cual'] }}"
                       wire:change="alternar('{{ $atajo['cual'] }}')"
                       @cannot('gestionar-ajustes') disabled @endcannot
                       class="peer sr-only">

                <span aria-hidden="true"
                      class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition
                             peer-focus-visible:ring-2 peer-focus-visible:ring-parte1/40 peer-focus-visible:ring-offset-2
                             {{ $atajo['puesto'] ? 'bg-parte1' : 'bg-slate-300' }}">
                    <span class="h-5 w-5 rounded-full bg-white shadow transition-transform
                                 {{ $atajo['puesto'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </span>

                <span class="min-w-0">
                    <span class="block font-semibold text-slate-900">
                        {{ $atajo['titulo'] }}
                        <span class="ml-1 font-mono text-[0.625rem] uppercase tracking-widest
                                     {{ $atajo['puesto'] ? 'text-parte1' : 'text-slate-400' }}">
                            {{ $atajo['puesto'] ? 'encendido' : 'apagado' }}
                        </span>
                    </span>
                    <span class="mt-0.5 block text-sm text-slate-600">{{ $atajo['texto'] }}</span>
                </span>
            </label>
        @endforeach
    </div>

    <p class="mt-3 text-xs text-slate-500">
        No se pueden apagar los dos primeros a la vez: la puerta se quedaría sin forma de marcar a nadie.
        La cara no cuenta para eso —se queda sin servir el día que se vacíe el índice—.
    </p>
</div>
