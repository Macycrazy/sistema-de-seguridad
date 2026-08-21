<?php

namespace App\Services\Auditoria;

use App\Models\Bitacora;
use App\Models\Persona;
use App\Models\User;
use App\Models\VehiculoFijo;

/**
 * Escribe la bitácora de auditoría. Un solo sitio por donde se anota, con un vocabulario cerrado
 * de acciones para que la pantalla las pueda etiquetar y filtrar.
 *
 * Anota SIEMPRE, aunque no haya sesión (un comando de consola): ahí el usuario queda nulo. Y nunca
 * revienta al que la llama: auditar es una consecuencia, no puede impedir la acción que se audita.
 */
class Auditoria
{
    // El rastro de sesión y de lectura (integrado del trabajo paralelo de Deiber Sella): quién
    // entró, quién lo intentó sin lograrlo, y quién miró los datos de quién.
    public const INGRESO_CORRECTO = 'ingreso-correcto';

    public const INGRESO_FALLIDO = 'ingreso-fallido';

    public const SALIO = 'salio';

    public const CONSULTO_CEDULA = 'consulto-cedula';

    public const VIO_FOTO = 'vio-foto';

    public const CONSULTO_HISTORICO = 'consulto-historico';

    public const EXPORTO_REGISTRO = 'exporto-registro';

    public const CREO_USUARIO = 'creo-usuario';

    public const DESACTIVO_USUARIO = 'desactivo-usuario';

    public const REACTIVO_USUARIO = 'reactivo-usuario';

    public const EDITO_USUARIO = 'edito-usuario';

    public const BORRO_USUARIO = 'borro-usuario';

    public const CAMBIO_ROL = 'cambio-rol';

    public const CAMBIO_CLAVE = 'cambio-clave';

    public const CAMBIO_PERMISOS = 'cambio-permisos';

    public const CAMBIO_ROLES = 'cambio-roles';

    public const CAMBIO_REGLAS = 'cambio-reglas';

    public const CAMBIO_OFICINAS = 'cambio-oficinas';

    public const CARGO_PERSONAL = 'cargo-personal';

    public const CAMBIO_ORGANIGRAMA = 'cambio-organigrama';

    public const DEPURO_DATOS = 'depuro-datos';

    public const RESPALDO = 'respaldo';

    // El estacionamiento: un vehículo que entra y, sobre todo, uno que sale. Hasta ahora no dejaba
    // ningún rastro —se sabía a quién se le entregó el carro, pero no quién lo dejó salir—.
    public const ANOTO_VEHICULO = 'anoto-vehiculo';

    public const SACO_VEHICULO = 'saco-vehiculo';

    /** La acción, en frase, para la pantalla. */
    public const ETIQUETAS = [
        self::INGRESO_CORRECTO => 'Entró al sistema',
        self::INGRESO_FALLIDO => 'Intento de ingreso fallido',
        self::SALIO => 'Cerró la sesión',
        self::CONSULTO_CEDULA => 'Consultó una cédula',
        self::VIO_FOTO => 'Vio una foto',
        self::CONSULTO_HISTORICO => 'Consultó un histórico',
        self::EXPORTO_REGISTRO => 'Exportó el registro',
        self::CREO_USUARIO => 'Creó un usuario',
        self::DESACTIVO_USUARIO => 'Desactivó un usuario',
        self::REACTIVO_USUARIO => 'Reactivó un usuario',
        self::EDITO_USUARIO => 'Editó un usuario',
        self::BORRO_USUARIO => 'Borró un usuario',
        self::CAMBIO_ROL => 'Cambió un rol',
        self::CAMBIO_CLAVE => 'Cambió una clave',
        self::CAMBIO_PERMISOS => 'Cambió permisos',
        self::CAMBIO_ROLES => 'Cambió los roles',
        self::CAMBIO_REGLAS => 'Cambió las reglas de tiempo',
        self::CAMBIO_OFICINAS => 'Cambió las oficinas',
        self::CARGO_PERSONAL => 'Cargó personal',
        self::CAMBIO_ORGANIGRAMA => 'Cambió el organigrama',
        self::DEPURO_DATOS => 'Depuró datos',
        self::RESPALDO => 'Respaldo',
        self::ANOTO_VEHICULO => 'Anotó un vehículo',
        self::SACO_VEHICULO => 'Sacó un vehículo',
    ];

    /**
     * Cuántos segundos hacen que dos anotaciones iguales cuenten como una.
     *
     * Es para lo que se dispara solo, como la consulta de cédula en la puerta: el campo busca en
     * cada pausa del tecleo, así que «25375258» pasa por «253752» y «2537525», y para quien mire
     * la auditoría eso fue una sola consulta. Lo que se anota de más entierra lo que importa.
     * (Idea tomada de Rastro::SEGUNDOS_DE_AGRUPACION, de Deiber.)
     */
    public const SEGUNDOS_DEDUP = 30;

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

    /**
     * Anota, salvo que el mismo usuario ya haya anotado lo mismo hace un instante. Para las
     * acciones que se repiten solas (la consulta de cédula, la foto vista): agrupa el ruido del
     * tecleo sin tapar dos consultas de verdad separadas.
     */
    public function anotaUnaVez(string $accion, ?string $sobre = null, ?string $detalle = null): void
    {
        $yaEsta = Bitacora::query()
            ->where('accion', $accion)
            ->where('usuario_id', auth()->id())
            ->where('sobre', $sobre)
            ->where('ocurrio_en', '>=', now()->subSeconds(self::SEGUNDOS_DEDUP))
            ->exists();

        if (! $yaEsta) {
            $this->anota($accion, $sobre, $detalle);
        }
    }

    // Atajos, para que cada enganche sea una línea que se lee sola.

    public function ingresoCorrecto(): void
    {
        $this->anota(self::INGRESO_CORRECTO);
    }

    /**
     * Un ingreso que no fue. Sin usuario cuando la clave no era —no se sabe quién estaba al
     * teclado y decirlo sería inventar—; con el nombre de la cuenta cuando la clave sí era pero
     * está desactivada, que eso sí se sabe y que alguien desactivado insista es un dato.
     */
    public function ingresoFallido(?string $cuenta = null, ?string $motivo = null): void
    {
        $this->anota(self::INGRESO_FALLIDO, $cuenta, $motivo);
    }

    public function salio(): void
    {
        $this->anota(self::SALIO);
    }

    /** La consulta de una cédula en la puerta. Con dedup: el tecleo dispara varias por consulta. */
    public function consultoCedula(string $cedula): void
    {
        $this->anotaUnaVez(self::CONSULTO_CEDULA, $cedula);
    }

    public function vioFoto(Persona $persona): void
    {
        $this->anotaUnaVez(self::VIO_FOTO, $this->identifica($persona));
    }

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

    public function editoUsuario(User $usuario): void
    {
        $this->anota(self::EDITO_USUARIO, $usuario->usuario);
    }

    public function borroUsuario(string $usuario): void
    {
        $this->anota(self::BORRO_USUARIO, $usuario);
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

    public function cambioRoles(string $detalle): void
    {
        $this->anota(self::CAMBIO_ROLES, null, $detalle);
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

    public function cambioOrganigrama(string $detalle): void
    {
        $this->anota(self::CAMBIO_ORGANIGRAMA, $detalle);
    }

    public function depuroDatos(string $detalle): void
    {
        $this->anota(self::DEPURO_DATOS, $detalle);
    }

    /** Entró un vehículo al estacionamiento. «Sobre» es la placa: por ahí se busca. */
    public function anotoVehiculo(VehiculoFijo $estadia): void
    {
        $this->anota(
            self::ANOTO_VEHICULO,
            $estadia->placa,
            trim($estadia->etiquetaTipo().' · '.($estadia->conductor_nombre ?: 'sin conductor anotado')),
        );
    }

    /** Salió un vehículo. A quién se le entregó va en el detalle; quién lo dejó salir, en el usuario. */
    public function sacoVehiculo(VehiculoFijo $estadia): void
    {
        $this->anota(
            self::SACO_VEHICULO,
            $estadia->placa,
            'Se lo llevó: '.($estadia->salida_conductor_nombre ?: 'sin conductor anotado'),
        );
    }

    public function respaldo(string $detalle): void
    {
        $this->anota(self::RESPALDO, $detalle);
    }

    /** Cómo se nombra a una persona en el rastro: su cédula, que es lo que se buscó. */
    private function identifica(Persona $persona): string
    {
        return $persona->cedula;
    }
}
