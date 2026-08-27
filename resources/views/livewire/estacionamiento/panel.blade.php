@php
    use App\Services\Estacionamiento\Estacionamiento;
    $est = app(Estacionamiento::class);
    $r = $this->resumen;
    $vehiculos = $this->vehiculos;
    $total = $r['total'];
@endphp

{{--
    Se refresca solo cada medio minuto.

    Antes no hacía falta: esta pantalla era la única que movía vehículos, así que lo que mostraba
    solo cambiaba por lo que se hacía en ella. Desde que la puerta también los anota y los saca,
    quien la tiene abierta ve un estado viejo —un vehículo que ya se fue sigue ahí— y no tiene por
    qué sospecharlo. El botón «Actualizar» se queda: sirve para no esperar.

    NO se sondea con un formulario abierto: un refresco a media escritura le mueve el sitio bajo
    las manos a quien está tecleando una placa.
--}}
<div wire:loading.class="opacity-60" class="transition-opacity"
     @if (! $agregandoFijo && ! $gestionandoFlota && $sacandoFijo === null) wire:poll.30s @endif>
    @if ($aviso !== '')
        <x-aviso class="mb-4" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    {{-- El contador que gobierna la pantalla: el total dentro, contra el aforo si está puesto. --}}
    <div class="flex flex-wrap items-center justify-between gap-4 rounded border-2 bg-white px-5 py-4
                {{ $total['lleno'] ? 'border-alto' : 'border-parte1' }}">
        <p class="flex items-baseline gap-3">
            <span class="text-4xl font-bold tabular-nums tracking-tight text-slate-900">{{ $total['dentro'] }}</span>
            <span class="font-mono text-xs font-semibold uppercase tracking-widest {{ $total['lleno'] ? 'text-alto' : 'text-parte1' }}">
                @if ($total['aforo'] > 0)
                    de {{ $total['aforo'] }} · {{ $total['lleno'] ? 'lleno' : 'quedan '.$total['libres'] }}
                @else
                    vehículos dentro
                @endif
            </span>
        </p>

        <x-boton variante="secundario" wire:click="actualizar" wire:loading.attr="disabled" wire:target="actualizar">
            <span wire:loading.remove wire:target="actualizar">Actualizar</span>
            <span wire:loading wire:target="actualizar">Mirando…</span>
        </x-boton>
    </div>

    {{-- Carros y motos, que no estacionan en el mismo sitio: cada uno con su cupo y sus libres. --}}
    <div class="mt-4 grid grid-cols-2 gap-4">
        @foreach (['carro' => 'Carros', 'moto' => 'Motos'] as $clave => $rotulo)
            @php $c = $r[$clave]; @endphp
            <x-tarjeta class="{{ $c['lleno'] ? 'border-t-4 border-t-alto' : '' }}">
                <p class="text-3xl font-bold tabular-nums text-slate-900">{{ $c['dentro'] }}</p>
                <p class="mt-1 font-mono text-xs uppercase tracking-widest text-slate-500">{{ $rotulo }}</p>
                @if ($c['aforo'] > 0)
                    <p class="mt-2 text-xs font-semibold {{ $c['lleno'] ? 'text-alto' : 'text-slate-500' }}">
                        {{ $c['lleno'] ? 'Lleno · '.$c['dentro'].'/'.$c['aforo'] : 'Quedan '.$c['libres'].' de '.$c['aforo'] }}
                    </p>
                @endif
            </x-tarjeta>
        @endforeach
    </div>

    {{-- Buscar por placa: «¿está el carro ABC123?», «¿de quién es este que estorba?». --}}
    <div class="mt-4">
        <x-campo type="search" nombre="busqueda" autocomplete="off"
                 placeholder="Buscar por placa…" wire:model.live.debounce.300ms="busqueda" />
    </div>

    {{-- Los que pernoctan: siguen dentro y entraron antes de hoy. Solo aparece si hay alguno. --}}
    @if ($this->pernoctan->isNotEmpty())
        <div class="mt-4 overflow-hidden rounded border-l-4 border-parte1 bg-parte1-suave/40">
            <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                <p class="font-mono text-xs font-bold uppercase tracking-widest text-parte1">
                    Pernoctan · {{ $this->pernoctan->count() }}
                </p>
                <p class="font-mono text-xs text-slate-500">se quedaron de noche</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead>
                        <tr class="border-y border-parte1/20 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                            <th class="px-4 py-2 font-semibold">Placa</th>
                            <th class="px-4 py-2 font-semibold">Puesto</th>
                            <th class="px-4 py-2 font-semibold">Vehículo</th>
                            <th class="px-4 py-2 font-semibold">Conductor</th>
                            <th class="px-4 py-2 font-semibold text-right">Desde</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-parte1/10">
                        @foreach ($this->pernoctan as $p)
                            <tr wire:key="pernocta-{{ $p->id }}">
                                <td class="px-4 py-2 font-mono text-base font-bold tracking-wider text-slate-900">{{ $p->placa ?: '—' }}</td>
                                <td class="px-4 py-2 font-mono font-semibold text-slate-700">{{ $p->puesto ?: '—' }}</td>
                                <td class="px-4 py-2 text-slate-600"><x-etiqueta :tipo="$p->tipo_vehiculo" tamano="chico" /> <span class="ml-1">{{ trim(($p->marca ?? '').' '.($p->color ?? '')) ?: '—' }}</span></td>
                                <td class="px-4 py-2 text-slate-600">{{ $p->conductor ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-mono text-xs text-slate-500">{{ $est->desde($p->ocurrio_en)->translatedFormat('D j M · g:i a') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Lo que hay dentro (o lo que coincide con la placa buscada). Cada vehículo es una estadía:
         se le asigna el puesto y se saca desde aquí, con su conductor. --}}
    <div class="mt-3 flex flex-wrap items-center justify-end gap-2">
        {{-- El catálogo de la flota es administración, no operación: el guardia anota y saca
             vehículos, pero dar de alta uno de la empresa es tocar un catálogo. --}}
        @can('gestionar-puestos')
            @unless ($gestionandoFlota)
                <x-boton variante="secundario" wire:click="abrirFlota">Flota de la empresa</x-boton>
            @endunless
        @endcan
        @unless ($agregandoFijo)
            <x-boton wire:click="abrirFijo">Anotar vehículo</x-boton>
        @endunless
    </div>

    <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[48rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Placa</th>
                    <th class="px-4 py-3 font-semibold">Vehículo</th>
                    <th class="px-4 py-3 font-semibold">Puesto</th>
                    <th class="px-4 py-3 font-semibold">Conductor</th>
                    <th class="px-4 py-3 font-semibold text-right">Lleva</th>
                    <th class="px-4 py-3 font-semibold text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($vehiculos as $v)
                    <tr wire:key="veh-{{ $v->id }}">
                        <td class="px-4 py-3 font-mono text-base font-bold tracking-wider text-slate-900">{{ $v->placa ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            <x-etiqueta :tipo="$v->tipo_vehiculo" tamano="chico" />
                            <span class="ml-1">{{ trim(($v->marca ?? '').' '.($v->color ?? '')) ?: '—' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($this->hayPuestos)
                                <select wire:change="asignarPuesto({{ $v->id }}, $event.target.value)"
                                        class="rounded border border-slate-300 bg-white px-2 py-1 font-mono text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-parte1/30">
                                    @foreach (($this->opcionesPorVehiculo[$v->id] ?? []) as $valor => $texto)
                                        <option value="{{ $valor }}" @selected((string) $valor === (string) $v->puesto_id)>{{ $texto }}</option>
                                    @endforeach
                                </select>
                            @else
                                <span class="font-mono font-semibold text-slate-700">{{ $v->puesto ?: '—' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $v->conductor ?: '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs font-semibold text-slate-700">
                            {{ $est->tiempoDentro($v->ocurrio_en) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-boton variante="peligro" tamano="chico"
                                     wire:click="abrirSalida({{ $v->id }})">Sacar</x-boton>
                        </td>
                    </tr>

                    {{-- Al sacar: quién se lo lleva (puede ser otro). --}}
                    @if ($sacandoFijo === $v->id)
                        <tr wire:key="salida-{{ $v->id }}" class="bg-alto-suave/30">
                            <td colspan="6" class="px-4 py-4">
                                <form wire:submit="confirmarSalida" class="flex flex-wrap items-end gap-3">
                                    {{-- Quién se lo lleva, elegido en vez de tecleado de memoria:
                                         el que lo trajo y los que ya marcaron su salida hoy —que
                                         es justo quien se va en un vehículo sin pasar a decirlo—. --}}
                                    @if ($this->quienesPudieronLlevarselo !== [])
                                        <div class="w-64">
                                            {{-- Id propio y modelo compartido con el campo de al
                                                 lado: elegir aquí rellena la cédula, y quien
                                                 prefiera teclearla la teclea. --}}
                                            <x-selector etiqueta="Quién se lo lleva" nombre="conductorSalidaLista"
                                                        wire:model.live="conductorSalidaCedula"
                                                        :opciones="['' => 'Otra persona…'] + $this->quienesPudieronLlevarselo"
                                                        :error="$errors->first('conductorSalida')" />
                                        </div>
                                    @endif
                                    <div class="w-40">
                                        <x-campo etiqueta="{{ $this->quienesPudieronLlevarselo !== [] ? '…o su cédula' : 'Cédula de quien lo saca' }}"
                                                 nombre="conductorSalidaCedula" inputmode="numeric" maxlength="9"
                                                 oninput="this.value = this.value.replace(/[^0-9]/g, '')" ayuda="Opcional."
                                                 wire:model="conductorSalidaCedula" :error="$errors->first('conductorSalida')" />
                                    </div>
                                    <div class="w-56">
                                        <x-campo etiqueta="…o nombre" nombre="conductorSalidaNombre" ayuda="Opcional." maxlength="120" wire:model="conductorSalidaNombre" />
                                    </div>
                                    <div class="flex items-center gap-2 pb-6">
                                        <x-boton type="submit" variante="peligro" tamano="chico">Sacar</x-boton>
                                        <x-boton type="button" variante="secundario" tamano="chico" wire:click="cancelarSalida">Cancelar</x-boton>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <x-tabla-vacia :columnas="6">
                        {{ trim($busqueda) === '' ? 'No hay vehículos dentro ahora mismo.' : 'Ninguna placa dentro coincide con «'.$busqueda.'».' }}
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Lo que ha hecho esa placa: no solo si está dentro ahora, sino todas las veces que ha
         estado, con quién entró, con quién salió y quién lo dejó pasar. Solo sale al buscar. --}}
    @if ($this->historialDePlaca->isNotEmpty())
        <div class="mt-4">
            <p class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">
                Historial de «{{ $busqueda }}»
            </p>

            <div class="mt-2 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
                <table class="w-full min-w-[48rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                            <th class="px-4 py-3 font-semibold">Placa</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 font-semibold">Entró</th>
                            <th class="px-4 py-3 font-semibold">Con</th>
                            <th class="px-4 py-3 font-semibold">Salió</th>
                            <th class="px-4 py-3 font-semibold">Con</th>
                            <th class="px-4 py-3 font-semibold">Puesto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->historialDePlaca as $h)
                            <tr wire:key="placa-{{ $loop->index }}">
                                <td class="px-4 py-3 font-mono font-bold tracking-wider text-slate-900">{{ $h->placa }}</td>
                                {{-- Dicho en la propia fila: buscar una placa saca también sus
                                     visitas pasadas, y ver la placa en pantalla se leía como que
                                     el vehículo sigue aquí. --}}
                                <td class="px-4 py-3">
                                    @if ($h->dentro)
                                        <span class="font-mono text-xs font-bold uppercase tracking-widest text-parte1">Dentro</span>
                                    @else
                                        <span class="font-mono text-xs uppercase tracking-widest text-slate-400">Ya salió</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">
                                    {{ $h->entro_en->translatedFormat('D j M · g:i a') }}
                                    @if ($h->entroPor)
                                        <span class="block text-slate-400">por {{ $h->entroPor }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $h->entroCon ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">
                                    @if ($h->dentro)
                                        <span class="text-slate-400">—</span>
                                    @else
                                        {{ $h->salio_en->translatedFormat('D j M · g:i a') }}
                                        @if ($h->salioPor)
                                            <span class="block text-slate-400">por {{ $h->salioPor }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $h->dentro ? '—' : ($h->salioCon ?: '—') }}</td>
                                <td class="px-4 py-3 font-mono font-semibold text-slate-700">{{ $h->puesto ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Anotar un vehículo (de la flota o a mano, con conductor y puesto) y gestionar la flota.

         Esto estuvo escondido tras «si hay puestos cargados», que dejó de tener sentido cuando el
         puesto pasó a ser opcional: un vehículo entra igual y se le asigna la plaza después. Los
         botones que lo abren SÍ se veían, así que sin plazas cargadas se pulsaban y no pasaba
         nada. --}}
    @if ($gestionandoFlota)
        <div class="mt-3 rounded border border-slate-200 bg-white p-4 shadow-sm">
            <p class="mb-2 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Flota de la empresa</p>
            <form wire:submit="guardarFlota" class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-campo etiqueta="Placa" nombre="placaFlota" maxlength="15" wire:model="placaFlota" :error="$errors->first('placaFlota')" />
                <x-selector etiqueta="Tipo" nombre="tipoFlota" :opciones="['carro' => 'Carro', 'moto' => 'Moto']" wire:model="tipoFlota" />
                <x-campo etiqueta="Marca" nombre="marcaFlota" ayuda="Opcional." maxlength="40" wire:model="marcaFlota" />
                <x-campo etiqueta="Color" nombre="colorFlota" ayuda="Opcional." maxlength="30" wire:model="colorFlota" />
                <x-boton type="submit">Agregar a la flota</x-boton>
            </form>

            @if ($this->flota->isNotEmpty())
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @foreach ($this->flota as $f)
                        <li class="flex items-center justify-between py-2" wire:key="flota-{{ $f->id }}">
                            <span class="font-mono font-semibold text-slate-800">{{ $f->placa }}
                                <span class="font-normal text-slate-400">· {{ $f->etiquetaTipo() }} {{ trim(($f->marca ?? '').' '.($f->color ?? '')) }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-3">
                                {{-- Agregarlo a la flota es cargarlo en el catálogo, no meterlo
                                     en el estacionamiento. Sin este botón había que cerrar
                                     esto, abrir «Anotar vehículo» y buscarlo en el
                                     desplegable, y nada lo decía. --}}
                                @if ($this->flotaDisponible->contains('id', $f->id))
                                    <button wire:click="anotarDeLaFlota({{ $f->id }})"
                                            class="text-sm font-semibold text-parte1 hover:underline">Anotar entrada</button>
                                @else
                                    <span class="font-mono text-xs uppercase tracking-widest text-slate-400">está dentro</span>
                                @endif

                                <button wire:click="eliminarFlota({{ $f->id }})" wire:confirm="¿Quitar {{ $f->placa }} de la flota?"
                                        class="text-sm font-semibold text-alto hover:underline">Quitar</button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-3">
                <x-boton type="button" variante="secundario" wire:click="cerrarFlota">Cerrar</x-boton>
            </div>
        </div>
    @endif

    @if ($agregandoFijo)
        @php
            $opFijo = ['' => 'Sin puesto todavía'];
            foreach ($this->puestosLibresFijo as $p) {
                $opFijo[$p->id] = $p->codigo.($p->zona ? ' · '.$p->zona : '').' ('.$p->etiquetaTipo().')';
            }
            $opFlota = ['' => 'Teclear a mano…'];
            foreach ($this->flotaDisponible as $f) {
                $opFlota[$f->id] = $f->descripcion();
            }
            $aMano = $flotaFija === '';
        @endphp
        <form wire:submit="agregarFijo" class="mt-3 rounded border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-selector etiqueta="De la flota" nombre="flotaFija" :opciones="$opFlota" wire:model.live="flotaFija" />
                @if ($aMano)
                    <x-campo etiqueta="Placa" nombre="placaFija" maxlength="15" wire:model="placaFija" :error="$errors->first('placaFija')" />
                    <x-selector etiqueta="Tipo" nombre="tipoFija" :opciones="['carro' => 'Carro', 'moto' => 'Moto']" wire:model.live="tipoFija" />
                @endif
                <x-selector etiqueta="Puesto" nombre="puestoFijo" :opciones="$opFijo" wire:model="puestoFijo" :error="$errors->first('puestoFijo')" />
                <x-campo etiqueta="Cédula del conductor" nombre="conductorCedulaFija" inputmode="numeric" maxlength="9"
                         oninput="this.value = this.value.replace(/[^0-9]/g, '')" ayuda="Opcional. Si está en el sistema."
                         wire:model="conductorCedulaFija" :error="$errors->first('conductorFija')" />
                <x-campo etiqueta="…o nombre del conductor" nombre="conductorNombreFija" ayuda="Opcional." maxlength="120" wire:model="conductorNombreFija" />
                @if ($aMano)
                    <x-campo etiqueta="Nota" nombre="notaFija" ayuda="Opcional." maxlength="120" wire:model="notaFija" />
                @endif
            </div>
            <div class="mt-4 flex items-center gap-3">
                <x-boton type="submit">Anotar</x-boton>
                <x-boton type="button" variante="secundario" wire:click="cancelarFijo">Cancelar</x-boton>
            </div>
        </form>
    @endif

    {{-- El registro del día: entradas Y salidas de vehículos hoy. Plegado, para no cargarlo si no
         se pide. --}}
    <div class="mt-6">
        <button type="button" wire:click="$toggle('verHistorial')"
                class="flex items-center gap-2 font-mono text-xs font-bold uppercase tracking-widest text-slate-500 hover:text-slate-800">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="h-4 w-4 transition-transform {{ $verHistorial ? 'rotate-90' : '' }}"><path d="m9 6 6 6-6 6"/></svg>
            Movimiento del día
        </button>

        @if ($verHistorial)
            {{-- Qué día se mira. Empieza en hoy; el botón vuelve a hoy sin pelear con el calendario. --}}
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div class="w-48">
                    <x-campo etiqueta="Día" nombre="fechaHistorial" type="date" wire:model.live="fechaHistorial" />
                </div>
                @unless ($this->historialEsHoy())
                    <div class="pb-1.5">
                        <x-boton type="button" variante="secundario" tamano="chico" wire:click="verHistorialDeHoy">Ver hoy</x-boton>
                    </div>
                @endunless
                <p class="pb-2.5 text-sm text-slate-500">
                    Qué vehículos entraron y salieron ese día, y con quién.
                </p>
            </div>

            <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                            <th class="px-4 py-3 font-semibold">Hora</th>
                            <th class="px-4 py-3 font-semibold">Mov.</th>
                            <th class="px-4 py-3 font-semibold">Placa</th>
                            <th class="px-4 py-3 font-semibold">Vehículo</th>
                            <th class="px-4 py-3 font-semibold">Conductor</th>
                            <th class="px-4 py-3 font-semibold">Registrado por</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->historial as $m)
                            <tr wire:key="hist-{{ $loop->index }}">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{{ $m->ocurrio_en->translatedFormat('g:i a') }}</td>
                                <td class="px-4 py-3"><x-etiqueta :tipo="$m->esEntrada ? 'entrada' : 'salida'" tamano="chico" /></td>
                                <td class="px-4 py-3 font-mono font-bold tracking-wider text-slate-900">{{ $m->placa ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $m->vehiculo->etiquetaTipo() }} {{ trim(($m->marca ?? '').' '.($m->color ?? '')) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $m->conductor ?: '—' }}</td>
                                {{-- Quién lo dejó entrar o salir, que no es lo mismo que a quién se
                                     le entregó el vehículo. --}}
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $m->registradoPor ?: '—' }}</td>
                            </tr>
                        @empty
                            <x-tabla-vacia :columnas="6">
                                @if ($this->historialEsHoy())
                                    Hoy no ha entrado ni salido ningún vehículo.
                                @else
                                    Ese día no entró ni salió ningún vehículo.
                                @endif
                            </x-tabla-vacia>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Reporte de pernoctas por noche: se elige una fecha y sale quién se quedó esa noche
         (personas y fijos). Plegado, para no consultarlo si no se pide. --}}
    <div class="mt-6">
        <button type="button" wire:click="$toggle('verReporte')"
                class="flex items-center gap-2 font-mono text-xs font-bold uppercase tracking-widest text-slate-500 hover:text-slate-800">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="h-4 w-4 transition-transform {{ $verReporte ? 'rotate-90' : '' }}"><path d="m9 6 6 6-6 6"/></svg>
            Pernoctas por noche
        </button>

        @if ($verReporte)
            <div class="mt-3">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-48">
                        <x-campo etiqueta="Noche del" nombre="fechaReporte" type="date" wire:model.live="fechaReporte" />
                    </div>
                    <p class="pb-2.5 text-sm text-slate-500">
                        Quién estaba dentro en la medianoche que cierra ese día.
                    </p>
                </div>

                <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                                <th class="px-4 py-3 font-semibold">Placa</th>
                                <th class="px-4 py-3 font-semibold">Puesto</th>
                                <th class="px-4 py-3 font-semibold">Vehículo</th>
                                <th class="px-4 py-3 font-semibold">Quién</th>
                                <th class="px-4 py-3 font-semibold text-right">Entró</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->reporteNoche as $r)
                                <tr wire:key="rep-{{ $loop->index }}">
                                    <td class="px-4 py-3 font-mono text-base font-bold tracking-wider text-slate-900">{{ $r->placa ?: '—' }}</td>
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-700">{{ $r->puesto ?: '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">
                                        <x-etiqueta :tipo="$r->tipo_vehiculo" tamano="chico" />
                                        <span class="ml-1">{{ trim(($r->marca ?? '').' '.($r->color ?? '')) ?: '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $r->quien }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs text-slate-500">{{ $r->entro_en->translatedFormat('D j M · g:i a') }}</td>
                                </tr>
                            @empty
                                <x-tabla-vacia :columnas="5">Esa noche no pernoctó ningún vehículo.</x-tabla-vacia>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
