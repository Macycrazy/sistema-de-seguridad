<?php

namespace App\Usuarios;

/**
 * Lo que se puede hacer en el sistema, por módulo y por acción.
 *
 * Cada caso es un gate con el mismo nombre, y quién lo tiene se guarda en la tabla
 * «permisos_de_rol»: se cambia desde la pantalla de roles, no tocando código.
 *
 * VER vs GESTIONAR: casi cada módulo se parte en dos —«ver» (entrar y mirar) y «gestionar»
 * (cambiar)—. Y GESTIONAR IMPLICA VER: quien puede gestionar un módulo puede verlo, aunque no tenga
 * marcado el «ver» (ver Permiso::implicadoPor() y Permisos::tiene()). Así no hay que marcar los dos.
 *
 * OJO CON LA DIFERENCIA, que es la que sostiene todo lo demás:
 *
 * - Un PERMISO dice a qué pantallas y acciones llega un rol. Es configurable.
 * - El ORDEN DE LOS ROLES dice a quién puede tocar cada quien —un supervisor no le pone la clave
 *   a un administrador— y NO es configurable. Vive en Rol::alcanza() y en GestionDeUsuarios.
 */
enum Permiso: string
{
    case VER_FOTO = 'ver-foto';

    case VER_REGISTRO = 'ver-registro';

    case EXPORTAR_REGISTRO = 'exportar-registro';

    case VER_PERSONAL = 'ver-personal';

    case GESTIONAR_PERSONAL = 'gestionar-personal';

    case VER_ORGANIGRAMA = 'ver-organigrama';

    case GESTIONAR_ORGANIGRAMA = 'gestionar-organigrama';

    case VER_USUARIOS = 'ver-usuarios';

    case GESTIONAR_USUARIOS = 'gestionar-usuarios';

    case VER_VISITAS = 'ver-visitas';

    case GESTIONAR_VISITAS = 'gestionar-visitas';

    case VER_EDIFICIO = 'ver-edificio';

    case GESTIONAR_EDIFICIO = 'gestionar-edificio';

    case VER_PUESTOS = 'ver-puestos';

    case GESTIONAR_PUESTOS = 'gestionar-puestos';

    case VER_AJUSTES = 'ver-ajustes';

    case GESTIONAR_AJUSTES = 'gestionar-ajustes';

    case VER_AUDITORIA = 'ver-auditoria';

    case GESTIONAR_RESPALDOS = 'gestionar-respaldos';

    case GESTIONAR_PERMISOS = 'gestionar-permisos';

    /** El módulo al que pertenece, para agrupar la pantalla de roles. */
    public function grupo(): string
    {
        return match ($this) {
            self::VER_FOTO => 'Puerta',
            self::VER_REGISTRO, self::EXPORTAR_REGISTRO => 'Registro',
            self::VER_PERSONAL, self::GESTIONAR_PERSONAL => 'Personal',
            self::VER_ORGANIGRAMA, self::GESTIONAR_ORGANIGRAMA => 'Organigrama',
            self::VER_USUARIOS, self::GESTIONAR_USUARIOS => 'Usuarios',
            self::VER_VISITAS, self::GESTIONAR_VISITAS => 'Visitas',
            self::VER_EDIFICIO, self::GESTIONAR_EDIFICIO => 'Edificio',
            self::VER_PUESTOS, self::GESTIONAR_PUESTOS => 'Puestos',
            self::VER_AJUSTES, self::GESTIONAR_AJUSTES => 'Ajustes',
            self::VER_AUDITORIA => 'Auditoría',
            self::GESTIONAR_RESPALDOS => 'Respaldos',
            self::GESTIONAR_PERMISOS => 'Roles',
        };
    }

    /**
     * El permiso «gestionar» que, si un rol lo tiene, le concede TAMBIÉN este «ver»: gestionar
     * implica ver. Null cuando este permiso no lo implica ninguno.
     *
     * El registro queda aparte a propósito: «exportar» no es «gestionar el registro», así que no
     * arrastra el «ver». Se puede tener uno sin el otro.
     */
    public function implicadoPor(): ?self
    {
        return match ($this) {
            self::VER_PERSONAL => self::GESTIONAR_PERSONAL,
            self::VER_ORGANIGRAMA => self::GESTIONAR_ORGANIGRAMA,
            self::VER_USUARIOS => self::GESTIONAR_USUARIOS,
            self::VER_VISITAS => self::GESTIONAR_VISITAS,
            self::VER_EDIFICIO => self::GESTIONAR_EDIFICIO,
            self::VER_PUESTOS => self::GESTIONAR_PUESTOS,
            self::VER_AJUSTES => self::GESTIONAR_AJUSTES,
            default => null,
        };
    }

    /** Si es un «ver» que otro permiso implica: en la pantalla se muestra atenuado cuando aplica. */
    public function esImplicado(): bool
    {
        return $this->implicadoPor() !== null;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::VER_FOTO => 'Ver la foto de una persona',
            self::VER_REGISTRO => 'Ver el registro',
            self::EXPORTAR_REGISTRO => 'Exportar el registro',
            self::VER_PERSONAL => 'Ver el personal',
            self::GESTIONAR_PERSONAL => 'Gestionar el personal',
            self::VER_ORGANIGRAMA => 'Ver el organigrama',
            self::GESTIONAR_ORGANIGRAMA => 'Gestionar el organigrama',
            self::VER_USUARIOS => 'Ver los usuarios',
            self::GESTIONAR_USUARIOS => 'Gestionar usuarios',
            self::VER_VISITAS => 'Ver las visitas esperadas',
            self::GESTIONAR_VISITAS => 'Gestionar visitas esperadas',
            self::VER_EDIFICIO => 'Ver las oficinas del edificio',
            self::GESTIONAR_EDIFICIO => 'Gestionar el edificio',
            self::VER_PUESTOS => 'Ver los puestos',
            self::GESTIONAR_PUESTOS => 'Gestionar los puestos',
            self::VER_AJUSTES => 'Ver los ajustes',
            self::GESTIONAR_AJUSTES => 'Cambiar los ajustes',
            self::VER_AUDITORIA => 'Ver la auditoría',
            self::GESTIONAR_RESPALDOS => 'Gestionar respaldos',
            self::GESTIONAR_PERMISOS => 'Gestionar permisos',
        };
    }

    public function explicacion(): string
    {
        return match ($this) {
            self::VER_FOTO => 'Sin esto, la pantalla de marcar no muestra la cara de quien se marca.',
            self::VER_REGISTRO => 'Entrar al registro y ver los movimientos y el histórico de cada quien.',
            self::EXPORTAR_REGISTRO => 'Sacar el registro a un archivo de Excel.',
            self::VER_PERSONAL => 'Entrar a la lista de trabajadores y buscarlos.',
            self::GESTIONAR_PERSONAL => 'Dar de alta, editar, importar y desactivar trabajadores.',
            self::VER_ORGANIGRAMA => 'Ver la estructura de unidades del CIIP.',
            self::GESTIONAR_ORGANIGRAMA => 'Crear, mover, renombrar y desactivar unidades del organigrama.',
            self::VER_USUARIOS => 'Ver la lista de cuentas que entran al sistema.',
            self::GESTIONAR_USUARIOS => 'Dar de alta, desactivar, borrar y cambiar claves y roles.',
            self::VER_VISITAS => 'Ver la agenda de visitas esperadas.',
            self::GESTIONAR_VISITAS => 'Agendar y quitar visitas esperadas.',
            self::VER_EDIFICIO => 'Ver el catálogo de oficinas del edificio.',
            self::GESTIONAR_EDIFICIO => 'Agregar, editar y quitar oficinas.',
            self::VER_PUESTOS => 'Ver el catálogo de plazas del estacionamiento.',
            self::GESTIONAR_PUESTOS => 'Agregar, editar, deshabilitar y quitar plazas del estacionamiento.',
            self::VER_AJUSTES => 'Ver las reglas de tiempo, umbrales y retención.',
            self::GESTIONAR_AJUSTES => 'Cambiar las reglas de tiempo, umbrales y retención.',
            self::VER_AUDITORIA => 'Quién consultó qué cédula, quién exportó y quién corrigió.',
            self::GESTIONAR_RESPALDOS => 'Crear y descargar copias de seguridad de la base. Un respaldo es TODA la data.',
            self::GESTIONAR_PERMISOS => 'Esta misma pantalla: qué puede hacer cada rol.',
        };
    }

    /**
     * Este permiso no se toca desde la pantalla.
     *
     * Solo uno: el de gestionar permisos. Si se pudiera quitar al administrador, el primer clic
     * equivocado dejaría la pantalla cerrada para siempre y habría que entrar a la base a mano.
     */
    public function esIntocable(): bool
    {
        return $this === self::GESTIONAR_PERMISOS;
    }

    /**
     * Quién lo tiene cuando el sistema se instala. Es lo que siembra la migración; a partir de ahí
     * manda la base.
     *
     * @return array<int, Rol>
     */
    public function porOmision(): array
    {
        return match ($this) {
            // El vigilante la necesita para comprobar que quien tiene delante es quien dice ser.
            self::VER_FOTO => [Rol::vigilante(), Rol::supervisor(), Rol::administrador()],

            self::VER_REGISTRO,
            self::EXPORTAR_REGISTRO,
            self::VER_VISITAS,
            self::GESTIONAR_VISITAS,
            self::VER_USUARIOS,
            self::GESTIONAR_USUARIOS => [Rol::supervisor(), Rol::administrador()],

            self::VER_AUDITORIA,
            self::VER_PERSONAL,
            self::GESTIONAR_PERSONAL,
            self::VER_ORGANIGRAMA,
            self::GESTIONAR_ORGANIGRAMA,
            self::VER_EDIFICIO,
            self::GESTIONAR_EDIFICIO,
            self::VER_PUESTOS,
            self::GESTIONAR_PUESTOS,
            self::VER_AJUSTES,
            self::GESTIONAR_AJUSTES,
            self::GESTIONAR_RESPALDOS,
            self::GESTIONAR_PERMISOS => [Rol::administrador()],
        };
    }
}
