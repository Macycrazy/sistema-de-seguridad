<?php

namespace App\Services\Auditoria;

use App\Models\Bitacora;
use App\Models\Persona;
use App\Models\User;

/**
 * Escribe la bitácora de auditoría. Un solo sitio por donde se anota, con un vocabulario cerrado
 * de acciones para que la pantalla las pueda etiquetar y filtrar.
 *
 * Anota SIEMPRE, aunque no haya sesión (un comando de consola): ahí el usuario queda nulo. Y nunca
 * revienta al que la llama: auditar es una consecuencia, no puede impedir la acción que se audita.
 */
class Auditoria
{
    public const CONSULTO_HISTORICO = 'consulto-historico';

    public const EXPORTO_REGISTRO = 'exporto-registro';

    public const CREO_USUARIO = 'creo-usuario';

    public const DESACTIVO_USUARIO = 'desactivo-usuario';

    public const REACTIVO_USUARIO = 'reactivo-usuario';

    public const CAMBIO_ROL = 'cambio-rol';

    public const CAMBIO_CLAVE = 'cambio-clave';

    public const CAMBIO_PERMISOS = 'cambio-permisos';

    public const CAMBIO_REGLAS = 'cambio-reglas';

    public const CAMBIO_OFICINAS = 'cambio-oficinas';

    public const CARGO_PERSONAL = 'cargo-personal';

    /** La acción, en frase, para la pantalla. */
    public const ETIQUETAS = [
        self::CONSULTO_HISTORICO => 'Consultó un histórico',
        self::EXPORTO_REGISTRO => 'Exportó el registro',
        self::CREO_USUARIO => 'Creó un usuario',
        self::DESACTIVO_USUARIO => 'Desactivó un usuario',
        self::REACTIVO_USUARIO => 'Reactivó un usuario',
        self::CAMBIO_ROL => 'Cambió un rol',
        self::CAMBIO_CLAVE => 'Cambió una clave',
        self::CAMBIO_PERMISOS => 'Cambió permisos',
        self::CAMBIO_REGLAS => 'Cambió las reglas de tiempo',
        self::CAMBIO_OFICINAS => 'Cambió las oficinas',
        self::CARGO_PERSONAL => 'Cargó personal',
    ];

    /** El punto único de escritura. */
    public function anota(string $accion, ?string $sobre = null, ?string $detalle = null): void
    {
        Bitacora::create([
            'usuario_id' => auth()->id(),
            'accion' => $accion,
            'sobre' => $sobre,
            'detalle' => $detalle,
            'ip' => request()->ip(),
            'ocurrio_en' => now(),
        ]);
    }

    // Atajos, para que cada enganche sea una línea que se lee sola.

    public function consultoHistorico(Persona $persona): void
    {
        $this->anota(self::CONSULTO_HISTORICO, $this->identifica($persona));
    }

    public function exportoRegistro(string $fecha): void
    {
        $this->anota(self::EXPORTO_REGISTRO, $fecha);
    }

    public function creoUsuario(User $usuario): void
    {
        $this->anota(self::CREO_USUARIO, $usuario->usuario, 'rol '.$usuario->rol->value);
    }

    public function desactivoUsuario(User $usuario): void
    {
        $this->anota(self::DESACTIVO_USUARIO, $usuario->usuario);
    }

    public function reactivoUsuario(User $usuario): void
    {
        $this->anota(self::REACTIVO_USUARIO, $usuario->usuario);
    }

    public function cambioRol(User $usuario, string $antes, string $ahora): void
    {
        $this->anota(self::CAMBIO_ROL, $usuario->usuario, "de $antes a $ahora");
    }

    public function cambioClave(User $usuario): void
    {
        $this->anota(self::CAMBIO_CLAVE, $usuario->usuario);
    }

    public function cambioPermisos(): void
    {
        $this->anota(self::CAMBIO_PERMISOS);
    }

    public function cambioReglas(): void
    {
        $this->anota(self::CAMBIO_REGLAS);
    }

    public function cambioOficinas(string $detalle): void
    {
        $this->anota(self::CAMBIO_OFICINAS, $detalle);
    }

    public function cargoPersonal(string $detalle): void
    {
        $this->anota(self::CARGO_PERSONAL, $detalle);
    }

    /** Cómo se nombra a una persona en el rastro: su cédula, que es lo que se buscó. */
    private function identifica(Persona $persona): string
    {
        return $persona->cedula;
    }
}
