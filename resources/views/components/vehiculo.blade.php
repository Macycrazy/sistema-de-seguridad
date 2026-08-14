@props([
    'error' => null,
    'errorTipo' => null,
    // Cuando la persona ya tiene un vehículo anotado, su clase no se elige: viene dada.
    'tipoFijado' => null,
])

{{--
    Las cuatro casillas del vehículo, agrupadas bajo un solo título, como en la planilla de papel
    que esto viene a sustituir.

    Es un <fieldset> de verdad y no un div con un texto encima: quien navegue con lector de
    pantalla oye «Vehículo» antes de cada casilla y sabe que las cuatro van juntas.

    NINGUNA es obligatoria: la mayoría de la gente entra caminando. Lo único que se exige, y lo
    exige el servidor en Vehiculo::exigirValido(), es que si se llena alguna esté la placa —
    «Toyota gris» sin placa no identifica ningún carro.

    Se usa tanto al dar de alta al invitado como al recibirlo cuando vuelve, así que las cuatro
    propiedades del componente Marcar («marca», «modelo», «color», «placa») son las mismas en los
    dos sitios y por eso el wire:model va escrito aquí dentro una sola vez.
--}}
<fieldset class="rounded border border-slate-200 p-4">
    <legend class="px-2 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
        Vehículo <span class="font-sans normal-case tracking-normal text-slate-400">· solo si viene en uno</span>
    </legend>

    {{--
        Carro o moto. Son dos botones y no una lista desplegable porque solo hay dos opciones y
        en la puerta se resuelve de un toque, sin abrir nada.

        Por debajo son <input type="radio"> de verdad, escondidos con «sr-only»: así funcionan
        con el teclado y con lector de pantalla, y el navegador se encarga de que solo uno pueda
        estar marcado. Lo que se ve es la etiqueta de cada uno.

        Empieza en «Carro» marcado, que es lo más común. Eso NO significa que haya vehículo: si
        las demás casillas están vacías no se guarda nada, ni el tipo.
    --}}
    @php
        $tipos = [
            \App\Services\Vehiculo::CARRO => 'Carro',
            \App\Services\Vehiculo::MOTO => 'Moto',
        ];
    @endphp
    <div class="mb-4">
        <p class="mb-1.5 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
            Tipo
        </p>
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($tipos as $valor => $texto)
                @php
                    // Si la persona ya tiene vehículo, el otro botón queda apagado: un vehículo
                    // no cambia de clase. Se apaga de verdad, con «disabled», no solo en gris.
                    $bloqueado = $tipoFijado !== null && $tipoFijado !== $valor;
                @endphp
                <label @class(['cursor-pointer', 'cursor-not-allowed' => $bloqueado])>
                    <input type="radio" name="tipoVehiculo" value="{{ $valor }}"
                           wire:model.live="tipoVehiculo" class="peer sr-only"
                           @disabled($bloqueado)>
                    <span class="block rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600
                                 peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white
                                 peer-disabled:border-slate-200 peer-disabled:bg-slate-50 peer-disabled:text-slate-300
                                 peer-focus-visible:ring-4 peer-focus-visible:ring-slate-900/20">
                        {{ $texto }}
                    </span>
                </label>
            @endforeach

            {{-- La salida cuando de verdad llegó en otra cosa. Sin esto, un vehículo mal
                 anotado no habría forma de corregirlo desde la pantalla. --}}
            @if ($tipoFijado !== null)
                <button type="button" wire:click="cambiarVehiculo"
                        class="ml-1 rounded px-2 py-1 text-sm font-semibold text-slate-500 underline
                               underline-offset-2 hover:text-slate-900">
                    Otro vehículo
                </button>
            @endif
        </div>

        @if ($errorTipo)
            <p class="mt-1.5 text-sm text-alto">{{ $errorTipo }}</p>
        @elseif ($tipoFijado !== null)
            <p class="mt-1.5 text-sm text-slate-500">
                Ya tiene este vehículo anotado. Si hoy llegó en otro, pulsa «Otro vehículo».
            </p>
        @endif
    </div>

    {{-- En el teléfono, dos y dos: cuatro casillas en fila quedan tan estrechas que no se lee
         ni la etiqueta. Desde tableta en adelante, las cuatro en fila como en la planilla. --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {{-- Los ejemplos van con «ej.» delante. Sin eso, un «Toyota» en gris claro se confunde
             con un Toyota ya escrito, y en la puerta se marca de pie y apurado: nadie se para a
             mirar de qué tono es la letra. --}}
        <x-campo etiqueta="Marca" nombre="marca" wire:model="marca" autocomplete="off" placeholder="ej. Toyota" />
        <x-campo etiqueta="Modelo" nombre="modelo" wire:model="modelo" autocomplete="off" placeholder="ej. Corolla" />
        <x-campo etiqueta="Color" nombre="color" wire:model="color" autocomplete="off" placeholder="ej. Gris" />

        {{--
            La placa se acomoda sola mientras se teclea: mayúsculas y sin guiones ni espacios.
            Se hace igual que en el campo de la cédula, y por la misma razón: si la casilla
            dejara ver «ab-123-cd» y en la base quedara «AB123CD», lo que se ve y lo que se
            guarda no serían lo mismo, y eso confunde a quien está en la puerta.

            Es comodidad para quien teclea, NO seguridad: el servidor la vuelve a normalizar en
            Vehiculo::normalizarPlaca(), porque cualquiera puede enviar lo que quiera sin pasar
            por esta pantalla.
        --}}
        <x-campo
            etiqueta="Placa"
            nombre="placa"
            wire:model="placa"
            autocomplete="off"
            placeholder="ej. AB123CD"
            class="font-mono"
            maxlength="{{ \App\Services\Vehiculo::LARGO_PLACA }}"
            oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9ÁÉÍÓÚÑ]/g, '')"
            :error="$error"
        />
    </div>
</fieldset>
