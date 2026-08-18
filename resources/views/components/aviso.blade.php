{{--
    La confirmación verde de que algo salió bien: «guardado», «creado», «marcado». Un solo estilo
    para todo el sistema —color de éxito «ok», no «parte 3»— y role=status para que un lector de
    pantalla lo anuncie sin interrumpir.
--}}
<div {{ $attributes->merge([
    'class' => 'rounded border border-ok/30 bg-ok-suave px-4 py-3 text-sm font-semibold text-ok',
]) }} role="status">
    {{ $slot }}
</div>
