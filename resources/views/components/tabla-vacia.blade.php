@props(['columnas' => 1])

{{--
    La fila que ocupa una tabla cuando no hay nada que mostrar. Un solo estilo para todas —mismo
    aire y mismo tono— en vez de repetir el <td colspan> a mano en cada pantalla.
--}}
<tr>
    <td colspan="{{ $columnas }}" class="px-4 py-10 text-center text-sm text-slate-500">
        {{ $slot }}
    </td>
</tr>
