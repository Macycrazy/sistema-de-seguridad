<?php

namespace App\Auditoria;

/**
 * Lo que queda anotado en el rastro.
 *
 * Un caso por cada cosa que el sistema considera digna de recordar. Los nombres van en singular y
 * con punto —«consulta.cedula»— para que se lean agrupados por tema al filtrar.
 *
 * La lista es cerrada a propósito: si algo no está aquí, no se anota, y añadir una acción obliga a
 * decidir también cómo se llama y qué se guarda en «detalle». Un rastro donde cada quien inventa
 * su etiqueta no se puede consultar.
 */
enum Accion: string
{
    // La puerta del sistema.
    case INGRESO_CORRECTO = 'ingreso.correcto';

    case INGRESO_FALLIDO = 'ingreso.fallido';

    case SALIDA = 'salida';

    // La puerta de verdad, la de la calle (parte 1).
    case CONSULTA_CEDULA = 'consulta.cedula';

    case MOVIMIENTO_REGISTRADO = 'movimiento.registrado';

    case FOTO_VISTA = 'foto.vista';

    // El registro (parte 2).
    case REGISTRO_BUSQUEDA = 'registro.busqueda';

    case REGISTRO_HISTORICO = 'registro.historico';

    case REGISTRO_EXPORTADO = 'registro.exportado';

    // La gestión (parte 3).
    case USUARIO_CREADO = 'usuario.creado';

    case USUARIO_DESACTIVADO = 'usuario.desactivado';

    case USUARIO_REACTIVADO = 'usuario.reactivado';

    case USUARIO_CLAVE_CAMBIADA = 'usuario.clave-cambiada';

    case USUARIO_ROL_CAMBIADO = 'usuario.rol-cambiado';

    case CLAVE_PROPIA_CAMBIADA = 'clave.propia-cambiada';

    case PERMISOS_CAMBIADOS = 'permisos.cambiados';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO_CORRECTO => 'Entró al sistema',
            self::INGRESO_FALLIDO => 'Intento de ingreso fallido',
            self::SALIDA => 'Cerró la sesión',
            self::CONSULTA_CEDULA => 'Consultó una cédula',
            self::MOVIMIENTO_REGISTRADO => 'Registró un movimiento',
            self::FOTO_VISTA => 'Vio la foto de una persona',
            self::REGISTRO_BUSQUEDA => 'Buscó en el registro',
            self::REGISTRO_HISTORICO => 'Abrió el histórico de una persona',
            self::REGISTRO_EXPORTADO => 'Exportó el registro',
            self::USUARIO_CREADO => 'Creó un usuario',
            self::USUARIO_DESACTIVADO => 'Desactivó un usuario',
            self::USUARIO_REACTIVADO => 'Reactivó un usuario',
            self::USUARIO_CLAVE_CAMBIADA => 'Le cambió la clave a alguien',
            self::USUARIO_ROL_CAMBIADO => 'Le cambió el rol a alguien',
            self::CLAVE_PROPIA_CAMBIADA => 'Cambió su propia clave',
            self::PERMISOS_CAMBIADOS => 'Cambió los permisos de un rol',
        };
    }

    /**
     * Esta acción se repite sola mientras se teclea, y hay que agruparla.
     *
     * Son las dos que cuelgan de un campo que busca en cada pausa del tecleo: la cédula de la
     * puerta y la búsqueda del registro. Escribir «25375258» dispara varias consultas de lo que
     * para quien mira la auditoría fue una sola. Ver Rastro::SEGUNDOS_DE_AGRUPACION.
     */
    public function seRepiteSola(): bool
    {
        return in_array($this, [self::CONSULTA_CEDULA, self::REGISTRO_BUSQUEDA], true);
    }
}
