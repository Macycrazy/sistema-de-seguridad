<?php

namespace App\Usuarios;

/**
 * Lo que se puede hacer en el sistema.
 *
 * Cada caso es un gate con el mismo nombre, y quién lo tiene se guarda en la tabla
 * «permisos_de_rol»: se cambia desde la pantalla de roles, no tocando código.
 *
 * OJO CON LA DIFERENCIA, que es la que sostiene todo lo demás:
 *
 * - Un PERMISO dice a qué pantallas llega un rol. Es configurable.
 * - El ORDEN DE LOS ROLES dice a quién puede tocar cada quien —un supervisor no le pone la clave
 *   a un administrador— y NO es configurable. Vive en Rol::alcanza() y en GestionDeUsuarios.
 *
 * Si el orden de los roles se pudiera editar desde una pantalla, cualquiera con acceso a esa
 * pantalla se ascendería solo, y no habría permiso que valiera.
 */
enum Permiso: string
{
    case VER_FOTO = 'ver-foto';

    case VER_REGISTRO = 'ver-registro';

    case EXPORTAR_REGISTRO = 'exportar-registro';

    case GESTIONAR_USUARIOS = 'gestionar-usuarios';

    case GESTIONAR_PERSONAL = 'gestionar-personal';

    case GESTIONAR_VISITAS = 'gestionar-visitas';

    case GESTIONAR_EDIFICIO = 'gestionar-edificio';

    case GESTIONAR_AJUSTES = 'gestionar-ajustes';

    case VER_AUDITORIA = 'ver-auditoria';

    case GESTIONAR_PERMISOS = 'gestionar-permisos';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VER_FOTO => 'Ver la foto de una persona',
            self::VER_REGISTRO => 'Ver el registro',
            self::EXPORTAR_REGISTRO => 'Exportar el registro',
            self::GESTIONAR_USUARIOS => 'Gestionar usuarios',
            self::GESTIONAR_PERSONAL => 'Gestionar personal',
            self::GESTIONAR_VISITAS => 'Gestionar visitas esperadas',
            self::GESTIONAR_EDIFICIO => 'Gestionar el edificio',
            self::GESTIONAR_AJUSTES => 'Ajustar los tiempos',
            self::VER_AUDITORIA => 'Ver la auditoría',
            self::GESTIONAR_PERMISOS => 'Gestionar permisos',
        };
    }

    public function explicacion(): string
    {
        return match ($this) {
            self::VER_FOTO => 'Sin esto, la pantalla de marcar no muestra la cara de quien se marca.',
            self::VER_REGISTRO => 'Es la lista completa del personal, con el histórico de cada quien.',
            self::EXPORTAR_REGISTRO => 'Sacar el día a un archivo que se lleva en un pendrive.',
            self::GESTIONAR_USUARIOS => 'Dar de alta, desactivar y cambiar claves y roles.',
            self::GESTIONAR_PERSONAL => 'Cargar y dar de alta a los trabajadores que se marcan en la puerta.',
            self::GESTIONAR_VISITAS => 'Agendar a quién se espera y ver la lista de visitas del día en la puerta.',
            self::GESTIONAR_EDIFICIO => 'Las oficinas del edificio que se ofrecen al marcar el piso de un invitado.',
            self::GESTIONAR_AJUSTES => 'Las reglas de tiempo del marcaje: los plazos entre entradas y salidas.',
            self::VER_AUDITORIA => 'Quién consultó qué cédula, quién exportó y quién corrigió.',
            self::GESTIONAR_PERMISOS => 'Esta misma pantalla.',
        };
    }

    /**
     * Este permiso no se toca desde la pantalla.
     *
     * Solo uno: el de gestionar permisos. Si se pudiera quitar al administrador, el primer clic
     * equivocado dejaría la pantalla cerrada para siempre y habría que entrar a la base a mano.
     * Y si se le pudiera dar a otro rol, ese rol se daría a sí mismo todo lo demás en dos clics.
     */
    public function esIntocable(): bool
    {
        return $this === self::GESTIONAR_PERMISOS;
    }

    /**
     * Quién lo tiene cuando el sistema se instala.
     *
     * Es la tabla del README, y es lo que siembra la migración. A partir de ahí manda la base.
     *
     * @return array<int, Rol>
     */
    public function porOmision(): array
    {
        return match ($this) {
            // El vigilante la necesita para comprobar que quien tiene delante es quien dice ser.
            self::VER_FOTO => [Rol::VIGILANTE, Rol::SUPERVISOR, Rol::ADMINISTRADOR],

            self::VER_REGISTRO,
            self::EXPORTAR_REGISTRO,
            // Agendar visitas es tarea de recepción/supervisión; el vigilante marca, no agenda.
            self::GESTIONAR_VISITAS,
            self::GESTIONAR_USUARIOS => [Rol::SUPERVISOR, Rol::ADMINISTRADOR],

            // Cargar la nómina es tarea del administrador. Se puede abrir a más desde /roles.
            self::VER_AUDITORIA,
            self::GESTIONAR_PERSONAL,
            self::GESTIONAR_EDIFICIO,
            self::GESTIONAR_AJUSTES,
            self::GESTIONAR_PERMISOS => [Rol::ADMINISTRADOR],
        };
    }
}
