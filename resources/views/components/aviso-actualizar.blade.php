@php
    // Solo en desarrollo: en el servidor las migraciones las corre quien despliega, y ahí este
    // aviso no le serviría a nadie.
    $pendientes = app()->environment('local')
        ? app(\App\Services\MigracionesPendientes::class)->listar()
        : [];
@endphp

@if ($pendientes)
    {{--
        El aviso de que hay cambios de otra parte sin aplicar.

        Va ARRIBA DEL TODO y en rojo a propósito. Las tres partes tocan las mismas tablas, y quien
        se baja los cambios de otro sin correr las migraciones se encuentra con errores de columna
        que no dicen nada de lo que pasa de verdad —«Unknown column vehiculos.tipo»— y pierde un
        rato largo buscando dónde se rompió algo que no está roto.

        No se puede cerrar: si se pudiera, se cerraría sin leerlo y volveríamos al principio.
    --}}
    <div class="border-b-2 border-alto bg-alto-suave px-6 py-4" role="alert">
        <div class="mx-auto flex max-w-5xl flex-col gap-3 sm:flex-row sm:items-start sm:gap-5">
            <p class="shrink-0 font-mono text-xs font-bold uppercase tracking-widest text-alto">
                Falta actualizar
            </p>

            <div class="min-w-0 flex-1">
                <p class="font-semibold text-slate-900">
                    Hay {{ count($pendientes) }}
                    {{ count($pendientes) === 1 ? 'migración sin correr' : 'migraciones sin correr' }}
                    en tu base de datos.
                </p>
                <p class="mt-1 text-sm text-slate-700">
                    Alguien cambió las tablas y tú todavía no lo tienes. Hasta que las corras, esta
                    pantalla puede fallar con errores de columna que no explican nada.
                </p>

                <p class="mt-3 font-mono text-xs uppercase tracking-widest text-slate-500">
                    Qué correr
                </p>
                <pre class="mt-1 overflow-x-auto rounded bg-white px-3 py-2 font-mono text-sm text-slate-900"><code>php artisan migrate</code></pre>

                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700 hover:text-slate-900">
                        Ver cuáles faltan
                    </summary>
                    <ul class="mt-2 space-y-1">
                        @foreach ($pendientes as $migracion)
                            <li class="font-mono text-xs text-slate-600">{{ $migracion }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-sm text-slate-600">
                        Lo que cambia cada una y por qué está en <code class="font-mono">docs/esquema.md</code>.
                    </p>
                </details>
            </div>
        </div>
    </div>
@endif
