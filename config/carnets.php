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

];
