@extends('layouts.app')

@section('titulo', 'Base visual')
@section('seccion', 'Base visual')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Base visual</h1>
    <p class="mt-3 max-w-2xl text-slate-600">
        Piezas ya hechas para que las tres partes se vean como un solo sistema. Se usan como
        etiquetas de Blade; no hace falta copiar clases de Tailwind a mano.
    </p>

    {{-- BOTONES --}}
    <section class="mt-10">
        <h2 class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Botones</h2>

        <x-tarjeta class="mt-3">
            <div class="flex flex-wrap items-center gap-3">
                <x-boton>Guardar</x-boton>
                <x-boton variante="secundario">Cancelar</x-boton>
                <x-boton variante="peligro">Desactivar</x-boton>
                <x-boton variante="entrada" tamano="chico">Entrada</x-boton>
                <x-boton disabled>Deshabilitado</x-boton>
            </div>

            <p class="mt-5 mb-2 text-sm text-slate-600">
                Los de la puerta van en grande: se tocan de pie y apurado.
            </p>
            <div class="flex flex-wrap gap-3">
                <x-boton variante="entrada" tamano="grande">ENTRADA</x-boton>
                <x-boton variante="salida" tamano="grande">SALIDA</x-boton>
            </div>

            <pre class="mt-5 overflow-x-auto rounded bg-slate-900 p-4 text-xs text-slate-100"><code>&lt;x-boton&gt;Guardar&lt;/x-boton&gt;
&lt;x-boton variante="entrada" tamano="grande"&gt;ENTRADA&lt;/x-boton&gt;

variante: primario · secundario · entrada · salida · peligro
tamano:   chico · normal · grande</code></pre>
        </x-tarjeta>
    </section>

    {{-- CAMPOS --}}
    <section class="mt-8">
        <h2 class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Campos</h2>

        <x-tarjeta class="mt-3">
            <div class="max-w-md space-y-5">
                <x-campo etiqueta="Cédula" nombre="cedula" tamano="grande" placeholder="0.000.000" />
                <x-campo etiqueta="Nombre y apellido" nombre="nombre" ayuda="Como aparece en el documento." />
                <x-campo etiqueta="A quién viene a ver" nombre="visita"
                         error="Este dato es obligatorio para un invitado." />
            </div>

            <pre class="mt-5 overflow-x-auto rounded bg-slate-900 p-4 text-xs text-slate-100"><code>&lt;x-campo etiqueta="Cédula" nombre="cedula" tamano="grande" /&gt;
&lt;x-campo etiqueta="Nombre" nombre="nombre" ayuda="..." /&gt;
&lt;x-campo etiqueta="Nombre" nombre="nombre" :error="$errors->first('nombre')" /&gt;</code></pre>
        </x-tarjeta>
    </section>

    {{-- ETIQUETAS --}}
    <section class="mt-8">
        <h2 class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Etiquetas</h2>

        <x-tarjeta class="mt-3">
            <div class="flex flex-wrap items-center gap-3">
                <x-etiqueta tipo="trabajador" />
                <x-etiqueta tipo="invitado" />
                <x-etiqueta tipo="entrada" />
                <x-etiqueta tipo="salida" />
                <x-etiqueta tipo="inactivo" />
            </div>
            <p class="mt-4 text-sm text-slate-600">
                El ámbar significa <strong class="font-semibold text-slate-900">invitado</strong> en
                todo el sistema, y no se usa para nada más.
            </p>

            <pre class="mt-5 overflow-x-auto rounded bg-slate-900 p-4 text-xs text-slate-100"><code>&lt;x-etiqueta tipo="invitado" /&gt;

tipo: trabajador · invitado · entrada · salida · inactivo</code></pre>
        </x-tarjeta>
    </section>

    {{-- TABLA --}}
    <section class="mt-8">
        <h2 class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Tabla</h2>

        <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                        <th class="px-4 py-3 font-semibold">Hora</th>
                        <th class="px-4 py-3 font-semibold">Persona</th>
                        <th class="px-4 py-3 font-semibold">Tipo</th>
                        <th class="px-4 py-3 font-semibold">Movimiento</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="px-4 py-3 font-mono text-slate-500">07:48</td>
                        <td class="px-4 py-3 font-medium">Nombre de ejemplo</td>
                        <td class="px-4 py-3"><x-etiqueta tipo="trabajador" /></td>
                        <td class="px-4 py-3"><x-etiqueta tipo="entrada" /></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-slate-500">08:02</td>
                        <td class="px-4 py-3 font-medium">Nombre de ejemplo</td>
                        <td class="px-4 py-3"><x-etiqueta tipo="invitado" /></td>
                        <td class="px-4 py-3"><x-etiqueta tipo="entrada" /></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-mono text-slate-500">09:15</td>
                        <td class="px-4 py-3 font-medium">Nombre de ejemplo</td>
                        <td class="px-4 py-3"><x-etiqueta tipo="trabajador" /></td>
                        <td class="px-4 py-3"><x-etiqueta tipo="salida" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-sm text-slate-500">Los datos son inventados. Copiar esta estructura para el registro.</p>
    </section>

    {{-- COLORES --}}
    <section class="mt-8 mb-4">
        <h2 class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Colores</h2>

        {{-- Las clases van escritas completas a propósito: Tailwind busca el texto tal cual
             en las vistas, así que una clase armada con variables no se genera. --}}
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="flex items-center gap-3 rounded border border-slate-200 bg-white p-3">
                <span class="h-9 w-9 shrink-0 rounded bg-marca"></span>
                <div class="min-w-0">
                    <p class="font-mono text-xs font-bold text-slate-900">marca</p>
                    <p class="truncate text-xs text-slate-500">Azul del CIIP · encabezado</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded border border-slate-200 bg-white p-3">
                <span class="h-9 w-9 shrink-0 rounded bg-parte1"></span>
                <div class="min-w-0">
                    <p class="font-mono text-xs font-bold text-slate-900">parte1</p>
                    <p class="truncate text-xs text-slate-500">Parte 1 · marcar e invitados</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded border border-slate-200 bg-white p-3">
                <span class="h-9 w-9 shrink-0 rounded bg-parte2"></span>
                <div class="min-w-0">
                    <p class="font-mono text-xs font-bold text-slate-900">parte2</p>
                    <p class="truncate text-xs text-slate-500">Parte 2 · el registro</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded border border-slate-200 bg-white p-3">
                <span class="h-9 w-9 shrink-0 rounded bg-parte3"></span>
                <div class="min-w-0">
                    <p class="font-mono text-xs font-bold text-slate-900">parte3</p>
                    <p class="truncate text-xs text-slate-500">Parte 3 · usuarios y roles</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded border border-slate-200 bg-white p-3">
                <span class="h-9 w-9 shrink-0 rounded bg-invitado"></span>
                <div class="min-w-0">
                    <p class="font-mono text-xs font-bold text-slate-900">invitado</p>
                    <p class="truncate text-xs text-slate-500">Solo para invitados</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded border border-slate-200 bg-white p-3">
                <span class="h-9 w-9 shrink-0 rounded bg-ok"></span>
                <div class="min-w-0">
                    <p class="font-mono text-xs font-bold text-slate-900">ok</p>
                    <p class="truncate text-xs text-slate-500">Entrada, confirmación</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded border border-slate-200 bg-white p-3">
                <span class="h-9 w-9 shrink-0 rounded bg-alto"></span>
                <div class="min-w-0">
                    <p class="font-mono text-xs font-bold text-slate-900">alto</p>
                    <p class="truncate text-xs text-slate-500">Error, dato inválido</p>
                </div>
            </div>
        </div>

        <pre class="mt-4 overflow-x-auto rounded bg-slate-900 p-4 text-xs text-slate-100"><code>bg-parte1   text-parte2   border-parte3   bg-invitado-suave   text-alto

Definidos en resources/css/app.css. Si hace falta un color nuevo,
se agrega ahí y se usa en todas las partes — no suelto en una vista.</code></pre>
    </section>
@endsection
