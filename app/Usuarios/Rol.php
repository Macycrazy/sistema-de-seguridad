<?php

namespace App\Usuarios;

/**
 * Los tres roles del sistema.
 *
 * Es la única definición: la usan el modelo, el middleware, los gates y las pantallas, así no se
 * pueden desajustar —la misma idea que Marcaje::DIGITOS_MINIMOS con la cédula.
 *
 * Son acumulativos, como los describe el README: el supervisor hace lo del vigilante y algo más,
 * y el administrador hace todo. Por eso hay un nivel y no una lista de permisos por rol.
 */
enum Rol: string
{
    /** Solo la pantalla de marcar. Nunca la lista completa del personal. */
    case VIGILANTE = 'vigilante';

    /** Además, el registro y las correcciones. */
    case SUPERVISOR = 'supervisor';

    /** Todo, más la gestión de usuarios y la auditoría. */
    case ADMINISTRADOR = 'administrador';

    /** Como se escribe en pantalla. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::VIGILANTE => 'Vigilante',
            self::SUPERVISOR => 'Supervisor',
            self::ADMINISTRADOR => 'Administrador',
        };
    }

    /**
     * En qué pantalla cae al entrar.
     *
     * El vigilante va derecho a la que va a tener abierta todo el turno; los demás, al inicio.
     * Esto es comodidad, no permiso: lo que cada quien puede ver se decide en el servidor.
     */
    public function pantallaDeInicio(): string
    {
        return $this === self::VIGILANTE ? 'marcar' : 'inicio';
    }

    /**
     * Este rol llega a lo que pide el otro.
     *
     * `Rol::ADMINISTRADOR->alcanza(Rol::SUPERVISOR)` es cierto; al revés no.
     */
    public function alcanza(self $minimo): bool
    {
        return $this->nivel() >= $minimo->nivel();
    }

    /**
     * Cuánto alcanza cada rol. No se guarda en la base: si mañana cambia el orden, cambia aquí
     * y ya, sin migración ni filas que corregir.
     */
    private function nivel(): int
    {
        return match ($this) {
            self::VIGILANTE => 1,
            self::SUPERVISOR => 2,
            self::ADMINISTRADOR => 3,
        };
    }
}
