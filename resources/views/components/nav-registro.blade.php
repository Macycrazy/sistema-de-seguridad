{{--
    El submenú del registro: sus tres vistas del mismo dato —los movimientos, sus cuentas y lo que
    merece atención— ahora que no son entradas sueltas del menú de arriba. Cada una revisa su propio
    permiso; Reportes y Alertas comparten «ver-registro» con los movimientos, así que si se ve una,
    se ven las tres.
--}}
<nav {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap gap-1 border-b border-slate-200']) }} aria-label="Vistas del registro">
    @php
        $vistas = [
            ['ruta' => 'registro', 'texto' => 'Movimientos'],
            ['ruta' => 'reportes', 'texto' => 'Reportes'],
            ['ruta' => 'alertas', 'texto' => 'Alertas'],
        ];
    @endphp

    @foreach ($vistas as $v)
        @php $activo = request()->routeIs($v['ruta']); @endphp
        <a href="{{ route($v['ruta']) }}"
           @if ($activo) aria-current="page" @endif
           class="-mb-px border-b-2 px-3 py-2 text-sm font-semibold transition
                  {{ $activo
                        ? 'border-marca text-marca'
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}">
            {{ $v['texto'] }}
        </a>
    @endforeach
</nav>
