@props([
    'error' => null,
    'errorTipo' => null,
    // Los vehículos que la persona ya tiene anotados. Vacío en el alta de un invitado, que
    // todavía no tiene ninguno: ahí solo se teclea.
    'vehiculos' => null,
    // Qué está marcado ahora mismo. Se pasa a mano porque un componente de Blade no ve las
    // propiedades del componente de Livewire que lo usa.
    'traeHoy' => '',
])

@php
    use App\Services\DatosVehiculo;
    use App\Livewire\Marcar;

    $vehiculos = $vehiculos ?? collect();
    $tieneLista = $vehiculos->isNotEmpty();

    $tipos = [
        DatosVehiculo::CARRO => 'Carro',
        DatosVehiculo::MOTO => 'Moto',
    ];
@endphp

{{--
    El vehículo con el que llega, agrupado bajo un solo título como en la planilla de papel que
    esto viene a sustituir.

    Es un <fieldset> de verdad y no un div con un texto encima: quien navegue con lector de
    pantalla oye «Vehículo» antes de cada casilla y sabe que todo va junto.

    Hay dos formas de rellenarlo, y la pantalla elige sola cuál toca:

      · CON LISTA — la persona ya tiene vehículos anotados. Se SEÑALA cuál trae hoy, sin teclear
        nada. Es lo normal: quien tiene carro y moto viene en uno de los dos, y el vigilante solo
        marca cuál. «A pie» y «Otro…» son dos opciones más de la misma lista.

      · SIN LISTA — el alta de un invitado, o alguien que trae uno que no tenía anotado. Ahí sí
        se teclea, y al marcar se le suma a su ficha para que la próxima vez ya salga en la lista.
--}}
<fieldset class="rounded border border-slate-200 p-4">
    <legend class="px-2 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
        Vehículo <span class="font-sans normal-case tracking-normal text-slate-400">· solo si viene en uno</span>
    </legend>

    @if ($tieneLista)
        {{--
            La casilla de qué trae hoy. Son <input type="radio"> de verdad escondidos con
            «sr-only»: así funcionan con el teclado y con lector de pantalla, y el navegador se
            encarga de que solo uno pueda estar marcado. Lo que se ve es la etiqueta de cada uno.
        --}}
        <p class="mb-2 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
            ¿Qué trae hoy?
        </p>

        {{--
            En pastillas y en fila, no en renglones apilados: son pocas opciones y cortas —«a pie»
            y una placa—, y así se ven todas de un golpe sin ocupar media pantalla del teléfono.
            Lo que hay debajo de cada una sigue siendo un <input type="radio"> de verdad.
        --}}
        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Qué trae hoy">
            {{-- Primero, porque es lo más común con diferencia: la mayoría de la gente entra
                 caminando. Lo que más se marca tiene que estar donde primero se mira. --}}
            <label class="cursor-pointer">
                <input type="radio" name="traeHoy" value="{{ Marcar::A_PIE }}"
                       wire:model.live="traeHoy" class="peer sr-only">
                <span class="block rounded-full border border-slate-300 px-4 py-2 text-sm text-slate-600
                             peer-checked:border-parte1 peer-checked:bg-parte1-suave peer-checked:font-semibold
                             peer-checked:text-parte1
                             peer-focus-visible:ring-4 peer-focus-visible:ring-parte1/25">
                    A pie
                </span>
            </label>

            @foreach ($vehiculos as $v)
                <label class="cursor-pointer" wire:key="veh-{{ $v->id }}">
                    <input type="radio" name="traeHoy" value="{{ $v->placa }}"
                           wire:model.live="traeHoy" class="peer sr-only">
                    {{-- La clase encima y la placa debajo: es como se reconoce un vehículo de
                         lejos, «la moto» primero y el número después. Marca, modelo y color no
                         caben en una pastilla y tampoco hacen falta para señalar cuál trae —van
                         en el title, para quien tenga dos del mismo tipo—. --}}
                    {{-- OJO: «peer-checked» solo alcanza a los HERMANOS del radio, no a lo que
                         haya dentro de ellos. Por eso el marcado lo llevan el borde y el fondo de
                         esta pastilla —que sí es hermana— y no el color de los renglones de
                         adentro, donde no se aplicaría nada. --}}
                    <span title="{{ trim($v->marca.' '.$v->modelo.' '.$v->color) ?: 'Sin más datos anotados' }}"
                          class="block rounded-full border border-slate-300 px-4 py-1.5 text-center
                                 peer-checked:border-parte1 peer-checked:bg-parte1-suave
                                 peer-focus-visible:ring-4 peer-focus-visible:ring-parte1/25">
                        <span class="block text-sm font-semibold text-slate-700">
                            {{ $v->esMoto() ? 'Moto' : 'Carro' }}
                        </span>
                        <span class="block font-mono text-[0.625rem] uppercase tracking-widest text-slate-500">
                            {{ $v->placa }}
                        </span>
                    </span>
                </label>
            @endforeach

            {{-- Y la salida para cuando trae uno que no está en su lista. --}}
            <label class="cursor-pointer">
                <input type="radio" name="traeHoy" value="{{ Marcar::OTRO }}"
                       wire:model.live="traeHoy" class="peer sr-only">
                <span class="block rounded-full border border-dashed border-slate-300 px-4 py-2 text-sm text-slate-600
                             peer-checked:border-solid peer-checked:border-parte1 peer-checked:bg-parte1-suave
                             peer-checked:font-semibold peer-checked:text-parte1
                             peer-focus-visible:ring-4 peer-focus-visible:ring-parte1/25">
                    Otro…
                </span>
            </label>
        </div>
    @endif

    {{-- Las casillas de teclear. Con lista, solo salen si se marcó «Otro…». --}}
    @if (! $tieneLista || $traeHoy === Marcar::OTRO)
        <div @class(['mt-4 border-t border-slate-100 pt-4' => $tieneLista])>
            @if ($tieneLista)
                <p class="mb-3 text-sm text-slate-500">
                    Al marcarlo se le suma a su ficha, y la próxima vez ya sale en la lista.
                </p>
            @endif

            <div class="mb-4">
                <p class="mb-1.5 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
                    Tipo
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($tipos as $valor => $texto)
                        <label class="cursor-pointer">
                            <input type="radio" name="tipoVehiculo" value="{{ $valor }}"
                                   wire:model.live="tipoVehiculo" class="peer sr-only">
                            <span class="block rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600
                                         peer-checked:border-parte1 peer-checked:bg-parte1-suave peer-checked:text-parte1
                                         peer-focus-visible:ring-4 peer-focus-visible:ring-parte1/25">
                                {{ $texto }}
                            </span>
                        </label>
                    @endforeach
                </div>

                @if ($errorTipo)
                    <p class="mt-1.5 text-sm text-alto">{{ $errorTipo }}</p>
                @endif
            </div>

            {{-- En el teléfono, dos y dos: cuatro casillas en fila quedan tan estrechas que no
                 se lee ni la etiqueta. Desde tableta en adelante, las cuatro en fila. --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                {{-- Los ejemplos van con «ej.» delante. Sin eso, un «Toyota» en gris claro se
                     confunde con un Toyota ya escrito, y en la puerta se marca de pie y
                     apurado: nadie se para a mirar de qué tono es la letra. --}}
                <x-campo etiqueta="Marca" nombre="marca" wire:model="marca" autocomplete="off" placeholder="ej. Toyota" />
                <x-campo etiqueta="Modelo" nombre="modelo" wire:model="modelo" autocomplete="off" placeholder="ej. Corolla" />
                <x-campo etiqueta="Color" nombre="color" wire:model="color" autocomplete="off" placeholder="ej. Gris" />

                {{--
                    La placa se acomoda sola mientras se teclea: mayúsculas y sin guiones ni
                    espacios. Se hace igual que en el campo de la cédula, y por la misma razón:
                    si la casilla dejara ver «ab-123-cd» y en la base quedara «AB123CD», lo que
                    se ve y lo que se guarda no serían lo mismo.

                    Es comodidad para quien teclea, NO seguridad: el servidor la vuelve a
                    normalizar en DatosVehiculo::normalizarPlaca().
                --}}
                <x-campo
                    etiqueta="Placa"
                    nombre="placa"
                    wire:model="placa"
                    autocomplete="off"
                    placeholder="ej. AB123CD"
                    class="font-mono"
                    maxlength="{{ DatosVehiculo::LARGO_PLACA }}"
                    oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9ÁÉÍÓÚÑ]/g, '')"
                    :error="$error"
                />
            </div>
        </div>
    @endif
</fieldset>
