<?php

namespace App\Models;

use App\Usuarios\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Quien opera el sistema: el vigilante que marca, el supervisor que revisa y el administrador.
 *
 * No es lo mismo que una Persona. Una Persona es quien pasa por la puerta; un User es quien
 * teclea. Alguien puede ser las dos cosas, y entonces tendrá una fila en cada tabla: se reconocen
 * por la cédula, pero no hay clave foránea entre ellas. Enlazarlas obligaría a que todo el que
 * opera el sistema pase también por la puerta, y no tiene por qué.
 *
 * Aquí no se guarda el correo de nadie. La identidad es el nombre de usuario.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'usuario',
        'nombre',
        'cedula',
        'rol',
        'activo',
        'password',
    ];

    /**
     * La clave no sale nunca, ni en un dd() ni en un JSON. El «hashed» de abajo se encarga de que
     * tampoco entre en claro.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * Los que pueden entrar. Un usuario desactivado sigue existiendo —no se borra— para que el
     * rastro de la auditoría no quede apuntando al vacío.
     *
     * @param  Builder<User>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * La cédula se guarda en solo dígitos, igual que en «personas», para que las dos tablas
     * hablen del mismo número. Se normaliza al asignarla y no en quien llame, que es donde se
     * olvida.
     */
    public function setCedulaAttribute(?string $cedula): void
    {
        $cedula = Persona::normalizarCedula($cedula);

        $this->attributes['cedula'] = $cedula === '' ? null : $cedula;
    }

    public function esVigilante(): bool
    {
        return $this->rol === Rol::VIGILANTE;
    }

    public function esSupervisor(): bool
    {
        return $this->rol === Rol::SUPERVISOR;
    }

    public function esAdministrador(): bool
    {
        return $this->rol === Rol::ADMINISTRADOR;
    }

    /** Su rol llega a lo que se le pide. Ver Rol::alcanza(). */
    public function alcanza(Rol $minimo): bool
    {
        return $this->rol instanceof Rol && $this->rol->alcanza($minimo);
    }

    /** El nombre corto para la barra de arriba: «Deiber S.» */
    public function nombreCorto(): string
    {
        $palabras = preg_split('/\s+/', trim((string) $this->nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($palabras) < 2) {
            return $palabras[0] ?? '';
        }

        return $palabras[0].' '.mb_strtoupper(mb_substr($palabras[1], 0, 1)).'.';
    }
}
