<?php

return [

    /*
    |--------------------------------------------------------------------------
    | El sistema de carnets
    |--------------------------------------------------------------------------
    |
    | De dónde salen las fotos del personal. El sistema de carnets ya las tiene, una por cédula,
    | así que en vez de volver a pedirlas se traen de ahí al dar de alta a un trabajador.
    |
    | «fotos» acepta dos formas, y el servicio distingue una de otra sola:
    |
    |   · Una RUTA de archivos, cuando los dos sistemas están en la misma máquina o en un disco
    |     compartido:   /var/www/carnets/public/imgs/usuarios
    |
    |   · Una URL, cuando el carnets vive en otro servidor de la red interna:
    |     http://172.17.1.23:8000/imgs/usuarios
    |
    | Se busca «{cedula}.jpg» (y .jpeg / .png). Si no está, el trabajador se da de alta igual, sin
    | foto: la pantalla cae a las iniciales. Traer la foto nunca puede impedir cargar la nómina.
    |
    */

    'fotos' => env('CARNETS_FOTOS'),

    /*
    | La dirección base del sistema de carnets para consultarlo por su API (verificar un QR). Es
    | una URL de la red interna: http://172.21.140.245:8000 (o http://127.0.0.1:8000 si los dos
    | corren en la misma máquina, que además esquiva la VPN porque el loopback no pasa por ella).
    | La puerta le manda el contenido del QR y carnets responde si el carnet es válido y de quién.
    */
    'url' => env('CARNETS_URL'),

    /*
    | Cuánto se espera por una foto cuando se traen por HTTP, en segundos. Corto a propósito: en
    | una importación de cientos de filas, un carnets caído no puede dejar el proceso colgado.
    */
    'timeout' => (int) env('CARNETS_TIMEOUT', 4),

    /*
    |--------------------------------------------------------------------------
    | La API del padrón
    |--------------------------------------------------------------------------
    |
    | El carnets expone su personal en «/api/seguridad/personal», protegido por un token en la
    | cabecera «X-API-Token» y por una lista de IPs. Se usa para dos cosas:
    |
    |   · traer el padrón de golpe, con el HASH de la foto de cada quien;
    |   · pedir cada foto por esa misma vía, ya autenticada.
    |
    | El hash es lo que hace que el reconocimiento facial no se quede viejo: el índice guarda la
    | cara que tenía esa persona el día que se miró, y comparando hashes se sabe A QUIÉN hay que
    | volver a mirar en vez de reindexar a los sesenta.
    |
    | Sin token configurado, todo esto queda apagado y el sistema sigue tirando de «fotos» de
    | arriba, que es como funcionaba antes.
    |
    */

    'token' => env('CARNETS_TOKEN'),

    /*
    | La base de la API. Si se deja vacía se usa «url» de arriba, que es lo normal: el padrón vive
    | en el mismo carnets.
    */

    'api' => env('CARNETS_API'),

];
