<?php

namespace App\Usuarios;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ValueError;

/**
 * Un rol del sistema: un nombre, un NIVEL (1, 2 o 3) y —aparte, en «permisos_de_rol»— lo que puede
 * hacer.
 *
 * Antes era un enum de tres casos fijos. Ahora los tres SIGUEN fijos (son la columna vertebral de
 * la jerarquía y no se borran), pero el administrador puede AÑADIR más desde la pantalla de roles,
 * cada uno anclado a uno de los tres niveles. Por eso dejó de ser un enum: los casos ya no se
 * conocen en tiempo de compilación.
 *
 * QUÉ NO CAMBIÓ, y sostiene todo lo demás:
 *
 * - El NIVEL decide quién puede tocar a quién (Rol::alcanza), igual que antes. Un rol nuevo de
 *   nivel 2 alcanza lo mismo que el supervisor, ni más ni menos. El nivel de los tres base es
 *   código (no se edita); el de un rol nuevo lo fija el admin al crearlo, y nunca puede pasar de 3.
 * - «gestionar-permisos» sigue siendo intocable y solo del administrador (ver Permiso), así que un
 *   rol nuevo no puede concederse la llave de esta misma pantalla.
 *
 * Cada slug tiene UNA sola instancia (flyweight): dos `Rol::desde('vigilante')` son el mismo objeto,
 * así `===` sigue distinguiendo roles como lo hacía el enum.
 */
final class Rol
{
    /** Los slugs de los tres roles base, para consultas y comparaciones. */
    public const VIGILANTE = 'vigilante';

    public const SUPERVISOR = 'supervisor';

    public const ADMINISTRADOR = 'administrador';

    /** El tope de la jerarquía. Ningún rol puede tener un nivel mayor. */
    public const NIVEL_MAXIMO = 3;

    /**
     * Los tres base, definidos en código para que no se puedan borrar ni corromper desde la base.
     * La migración los siembra también en la tabla, pero aquí manda esto.
     *
     * @var array<string, array{nombre: string, nivel: int}>
     */
    private const BASE = [
        self::VIGILANTE => ['nombre' => 'Vigilante', 'nivel' => 1],
        self::SUPERVISOR => ['nombre' => 'Supervisor', 'nivel' => 2],
        self::ADMINISTRADOR => ['nombre' => 'Administrador', 'nivel' => 3],
    ];

    /** @var array<string, self>  Una instancia por slug (flyweight). */
    private static array $instancias = [];

    private function __construct(
        public readonly string $value,
        public readonly string $nombre,
        public readonly int $nivel,
        public readonly bool $base,
    ) {}

    public static function vigilante(): self
    {
        return self::desde(self::VIGILANTE);
    }

    public static function supervisor(): self
    {
        return self::desde(self::SUPERVISOR);
    }

    public static function administrador(): self
    {
        return self::desde(self::ADMINISTRADOR);
    }

    /**
     * El rol de ese slug, o null si no existe. Los tres base salen del código; el resto, de la
     * tabla «roles». Cachea por slug para que `===` funcione y para no consultar de más.
     */
    public static function desde(?string $slug): ?self
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        if (isset(self::$instancias[$slug])) {
            return self::$instancias[$slug];
        }

        if (isset(self::BASE[$slug])) {
            return self::$instancias[$slug] = new self(
                $slug, self::BASE[$slug]['nombre'], self::BASE[$slug]['nivel'], base: true,
            );
        }

        // Roles nuevos: viven en la tabla. Antes de que exista la tabla (migraciones, arranque) o
        // sin app booteada (pruebas unitarias puras), solo hay base; el try lo cubre sin reventar.
        try {
            if (! Schema::hasTable('roles')) {
                return null;
            }

            $fila = DB::table('roles')->where('slug', $slug)->first();
        } catch (\Throwable) {
            return null;
        }

        if ($fila === null) {
            return null;
        }

        return self::$instancias[$slug] = new self(
            $fila->slug, $fila->nombre, (int) $fila->nivel, base: (bool) $fila->base,
        );
    }

    /** Como un enum: lanza si el slug no es un rol. */
    public static function from(string $slug): self
    {
        return self::desde($slug) ?? throw new ValueError("«{$slug}» no es un rol.");
    }

    /** Como un enum: null si el slug no es un rol. */
    public static function tryFrom(?string $slug): ?self
    {
        return self::desde($slug);
    }

    /**
     * Todos los roles: los tres base más los que haya creado el administrador. Ordenados por nivel
     * (de menos a más) y, dentro del nivel, base primero.
     *
     * @return array<int, self>
     */
    public static function cases(): array
    {
        $roles = self::base();

        try {
            $hayTabla = Schema::hasTable('roles');
        } catch (\Throwable) {
            $hayTabla = false;
        }

        if ($hayTabla) {
            foreach (DB::table('roles')->where('base', false)->orderBy('nivel')->orderBy('nombre')->get() as $fila) {
                $rol = self::desde($fila->slug);

                if ($rol !== null) {
                    $roles[] = $rol;
                }
            }
        }

        usort($roles, fn (self $a, self $b) => [$a->nivel, $a->base ? 0 : 1, $a->nombre] <=> [$b->nivel, $b->base ? 0 : 1, $b->nombre]);

        return $roles;
    }

    /**
     * Los tres roles base, siempre.
     *
     * @return array<int, self>
     */
    public static function base(): array
    {
        return array_map(fn (string $slug) => self::desde($slug), array_keys(self::BASE));
    }

    /** Olvida la caché. Para después de crear/editar/borrar un rol, o entre pruebas. */
    public static function olvidar(): void
    {
        self::$instancias = [];
    }

    /** Como se escribe en pantalla. */
    public function etiqueta(): string
    {
        return $this->nombre;
    }

    /**
     * En qué pantalla cae al entrar. El nivel 1 (como el vigilante) va derecho a marcar; los demás,
     * al inicio. Es comodidad, no permiso.
     */
    public function pantallaDeInicio(): string
    {
        return $this->nivel <= 1 ? 'marcar' : 'inicio';
    }

    /**
     * Este rol llega a lo que pide el otro. `administrador->alcanza(supervisor)` es cierto; al revés
     * no. Es por nivel, así un rol nuevo de nivel 2 alcanza igual que el supervisor.
     */
    public function alcanza(self $minimo): bool
    {
        return $this->nivel >= $minimo->nivel;
    }

    /** Si es el mismo rol (por slug), venga como venga. */
    public function es(self|string $otro): bool
    {
        return $this->value === ($otro instanceof self ? $otro->value : $otro);
    }

    public function esVigilante(): bool
    {
        return $this->value === self::VIGILANTE;
    }

    public function esSupervisor(): bool
    {
        return $this->value === self::SUPERVISOR;
    }

    public function esAdministrador(): bool
    {
        return $this->value === self::ADMINISTRADOR;
    }

    /** Uno de los tres fijos: no se borra ni se le cambia el nivel desde la pantalla. */
    public function esBase(): bool
    {
        return $this->base;
    }

    /** Para bindings de consulta (`where('rol', $rol)`) y donde se espere el slug como texto. */
    public function __toString(): string
    {
        return $this->value;
    }
}
