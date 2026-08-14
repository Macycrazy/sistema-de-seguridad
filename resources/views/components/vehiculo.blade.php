@props([
    'error' => null,
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

    {{-- En el teléfono, dos y dos: cuatro casillas en fila quedan tan estrechas que no se lee
         ni la etiqueta. Desde tableta en adelante, las cuatro en fila como en la planilla. --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-campo etiqueta="Marca" nombre="marca" wire:model="marca" autocomplete="off" placeholder="Toyota" />
        <x-campo etiqueta="Modelo" nombre="modelo" wire:model="modelo" autocomplete="off" placeholder="Corolla" />
        <x-campo etiqueta="Color" nombre="color" wire:model="color" autocomplete="off" placeholder="Gris" />

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
            placeholder="AB123CD"
            class="font-mono"
            maxlength="{{ \App\Services\Vehiculo::LARGO_PLACA }}"
            oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9ÁÉÍÓÚÑ]/g, '')"
            :error="$error"
        />
    </div>
</fieldset>
