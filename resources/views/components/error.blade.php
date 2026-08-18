{{--
    El aviso rojo de que algo no se pudo: un dato que no se entiende, una acción que falló. Un solo
    estilo, y role=alert porque suele aparecer a mitad de una acción y conviene que se anuncie ya.
--}}
<div {{ $attributes->merge([
    'class' => 'rounded border border-alto/40 bg-alto-suave px-4 py-3 text-sm text-alto',
]) }} role="alert">
    {{ $slot }}
</div>
