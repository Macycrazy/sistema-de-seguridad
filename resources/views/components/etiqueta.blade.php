@props(['tipo' => 'trabajador', 'tamano' => 'normal'])

@php
    // Un solo lugar decide de qué color se ve cada cosa en todo el sistema. Si una pantalla nueva
    // necesita una etiqueta, se agrega aquí un tipo —no se inventa un pill a mano en la vista—,
    // para que el color y el tamaño no se desparramen.
    [$clases, $texto] = match ($tipo) {
        // El valor «invitado» es el que se guarda en la base y el que usan las otras partes; lo
        // que cambia es cómo se le llama en pantalla. Ver docs/esquema.md.
        'invitado' => ['bg-invitado-suave text-invitado', 'VISITANTE'],
        'entrada' => ['bg-ok-suave text-ok', 'ENTRADA'],
        'salida' => ['bg-slate-100 text-slate-600', 'SALIDA'],
        'inactivo' => ['bg-alto-suave text-alto', 'INACTIVO'],

        // Vehículos del estacionamiento.
        'carro' => ['bg-parte1-suave text-parte1', 'CARRO'],
        'moto' => ['bg-parte1-suave text-parte1', 'MOTO'],

        // Estado de una visita esperada.
        'esperada' => ['bg-parte1-suave text-parte1', 'ESPERADA'],
        'llego' => ['bg-ok-suave text-ok', 'LLEGÓ'],
        'cancelada' => ['bg-slate-100 text-slate-500', 'CANCELADA'],
        'vencida' => ['bg-alto-suave text-alto', 'VENCIDA'],

        // Gravedad de una alerta.
        'urgente' => ['bg-alto-suave text-alto', 'URGENTE'],
        'aviso' => ['bg-slate-100 text-slate-500', 'AVISO'],

        default => ['bg-parte1-suave text-parte1', 'TRABAJADOR'],
    };

    // «chico» es para las tablas densas, donde la etiqueta normal pesa demasiado.
    $medidas = $tamano === 'chico'
        ? 'px-1.5 py-0.5 text-[10px] tracking-wide'
        : 'px-2.5 py-1 text-xs tracking-widest';
@endphp

<span {{ $attributes->merge([
    'class' => "inline-flex items-center rounded font-mono font-bold uppercase $medidas $clases",
]) }}>
    {{ trim($slot) ?: $texto }}
</span>
