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
    | Cuánto se espera por una foto cuando se traen por HTTP, en segundos. Corto a propósito: en
    | una importación de cientos de filas, un carnets caído no puede dejar el proceso colgado.
    */
    'timeout' => (int) env('CARNETS_TIMEOUT', 4),

];
