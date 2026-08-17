<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Las oficinas del edificio
    |--------------------------------------------------------------------------
    |
    | El catálogo de sitios a los que puede ir un invitado. La pantalla de marcar los ofrece como
    | botones: primero el piso y después la oficina, para no poner una lista de treinta delante de
    | alguien que está de pie en la puerta.
    |
    | Va aquí y no en la base de datos porque es la lista del edificio, no un dato del sistema:
    | cambia cuando alguien se muda de oficina, y entonces se edita este archivo. Tampoco se saca
    | de las fichas del personal, porque hay sitios donde no labora nadie —el LOBBY, un piso
    | recién desocupado— y aun así se va de visita a ellos.
    |
    | El código es el que ya se usa en las fichas: «2-1» es piso 2, oficina 1. Los que no llevan
    | guion —«7», «LOBBY»— son un sitio entero y se escogen de un toque, sin segundo paso.
    |
    | La GERENCIA de cada oficina no se escribe aquí: sale sola de las fichas del personal que
    | labora en ella, así que nunca puede contradecirlas. Una oficina sin nadie asignado se ofrece
    | igual, solo que sin nombre debajo.
    |
    | Y esto no es una validación: el vigilante puede escribir a mano un código que no esté en la
    | lista. Son atajos, no una reja.
    |
    */

    'oficinas' => [
        'LOBBY',
        'PB-1',
        '1-2',
        '1-7',
        '2-1',
        '2-2',
        '2-3',
        '2-4',
        '2-5',
        '2-6',
        '2-7',
        '3-1',
        '3-2',
        '3-3',
        '3-4',
        '3-5',
        '4-1',
        '4-2',
        '4-3',
        '4-4',
        '4-5',
        '4-6',
        '4-7',
        '4-8',
        '4-9',
        '7',
        '8-2',
    ],

];
