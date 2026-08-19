<?php

namespace App\Services;

use App\Models\Persona;
use App\Services\Carnets\FotoDelCarnet;
use App\Services\Organigrama\Organigrama;
use Illuminate\Validation\ValidationException;

/**
 * Dar de alta y actualizar a los trabajadores que se marcan en la puerta.
 *
 * Mientras la asociación con el sistema de carnets no exista, la nómina entra por aquí: una a una
 * desde la pantalla, o en bloque desde un Excel (App\Imports\TrabajadoresImport, que también llama
 * a este servicio, para que la validación sea la misma por los dos caminos).
 *
 * La pantalla no decide nada: pregunta aquí, igual que la de marcar le pregunta a Marcaje. Y aquí
 * se valida en el servidor aunque la pantalla ya haya validado, porque cualquiera puede mandar una
 * petición sin pasar por ella.
 *
 * Un trabajador NO es un invitado, ni una cuenta de usuario:
 *   · el invitado se da de alta en la puerta, con lo mínimo, y vive solo aquí;
 *   · la cuenta de usuario (User) es para ENTRAR al sistema, no para que la marquen.
 * Por eso una cédula de trabajador no puede pisar la de un invitado ya registrado, y al revés.
 */
class GestionDeTrabajadores
{
    /** Los entes válidos, con su etiqueta para la pantalla. */
    public const ENTES = [
        Persona::ENTE_CIIP => 'CIIP',
        Persona::ENTE_MARCA_PAIS => 'Marca País',
        Persona::ENTE_VENAPP => 'VENAPP',
    ];

    public function __construct(private FotoDelCarnet $fotos) {}

    /**
     * Da de alta o actualiza a un trabajador, buscándolo por su cédula.
     *
     * Es «updateOrCreate» a propósito: volver a cargar el mismo Excel corrige los datos en vez de
     * duplicar a nadie. La cédula es la identidad y no se cambia por esta vía.
     *
     * @throws ValidationException
     */
    public function guardar(
        string $cedula,
        string $nombre,
        ?string $ente = null,
        ?string $dependencia = null,
        ?string $piso = null,
        ?string $nacionalidad = null,
    ): Persona {
        $cedula = Persona::normalizarCedula($cedula);
        $nombre = trim($nombre);
        $ente = $this->enteValido($ente);
        $dependencia = $this->recorta($dependencia, 120);
        $piso = Persona::normalizarPiso($piso);
        $nacionalidad = trim((string) $nacionalidad);

        $this->exigirCedula($cedula);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => 'Hace falta el nombre del trabajador.',
            ]);
        }

        // Una cédula ya usada por un INVITADO no puede convertirse en trabajador sin querer: son
        // dos figuras distintas y mezclarlas ensuciaría el registro. Si de verdad esa persona pasó
        // a ser personal, primero hay que resolver el invitado a mano.
        $existente = Persona::where('cedula', $cedula)->first();

        if ($existente && $existente->esInvitado()) {
            throw ValidationException::withMessages([
                'cedula' => 'Esa cédula ya está registrada como invitado, no como trabajador.',
            ]);
        }

        $atributos = [
            'tipo' => Persona::TRABAJADOR,
            'nombre' => mb_strtoupper($nombre),
            'ente' => $ente,
            'dependencia' => $dependencia ? mb_strtoupper($dependencia) : null,
            'piso' => $piso,
            'activo' => true,
        ];

        // La nacionalidad solo se fija si vino: así el alta manual (que no la pregunta) no pisa la
        // que ya tuviera, y el import de la nómina de carnets —que sí la trae— la deja correcta.
        if ($nacionalidad !== '') {
            $atributos['nacionalidad'] = Persona::normalizarNacionalidad($nacionalidad);
        }

        $trabajador = Persona::updateOrCreate(['cedula' => $cedula], $atributos);

        $this->enlazarDepartamento($trabajador);
        $this->traerLaFoto($trabajador);

        return $trabajador;
    }

    /**
     * Enlaza al trabajador con su unidad del organigrama a partir del texto de «dependencia»,
     * creando la unidad si es la primera vez que aparece. Es aditivo: el texto se conserva; esto
     * solo llena la FK para poder agrupar. Sin dependencia, no hay nada que enlazar.
     */
    private function enlazarDepartamento(Persona $trabajador): void
    {
        if (! $trabajador->dependencia) {
            return;
        }

        $departamento = app(Organigrama::class)
            ->paraTexto($trabajador->dependencia, $trabajador->ente);

        if ($departamento && $trabajador->departamento_id !== $departamento->id) {
            $trabajador->update(['departamento_id' => $departamento->id]);
        }
    }

    /**
     * Trae la foto del sistema de carnets, si no la tiene ya. Best-effort: que el carnets no
     * responda, o que esa persona no tenga foto, no puede impedir darla de alta. Solo se busca
     * cuando falta, para no volver a bajar cientos de fotos en cada reimportación.
     */
    private function traerLaFoto(Persona $trabajador): void
    {
        if ($trabajador->tieneFoto()) {
            return;
        }

        $ruta = $this->fotos->traer($trabajador->cedula);

        if ($ruta !== null) {
            $trabajador->update(['foto_ruta' => $ruta]);
        }
    }

    /**
     * Le quita el acceso a un trabajador: deja de poder marcar, pero su histórico se conserva.
     *
     * No se borra nunca. Un trabajador con movimientos no se puede borrar (la base lo impide con
     * RESTRICT), y aunque no los tuviera, borrarlo dejaría al registro apuntando al vacío.
     */
    public function desactivar(Persona $trabajador): void
    {
        $trabajador->update(['activo' => false]);
    }

    public function reactivar(Persona $trabajador): void
    {
        $trabajador->update(['activo' => true]);
    }

    private function exigirCedula(string $cedula): void
    {
        // El mismo rango que la puerta: ni un número suelto ni un teléfono. Se apoya en las
        // constantes de Marcaje para que no se desajusten entre sí.
        $largo = mb_strlen($cedula);

        if ($largo < Marcaje::DIGITOS_MINIMOS || $largo > Marcaje::DIGITOS_MAXIMOS) {
            throw ValidationException::withMessages([
                'cedula' => 'La cédula debe tener entre '.Marcaje::DIGITOS_MINIMOS.' y '.Marcaje::DIGITOS_MAXIMOS.' dígitos.',
            ]);
        }
    }

    /** Acepta el ente vacío (queda sin asignar) o uno de los tres; cualquier otro se rechaza. */
    private function enteValido(?string $ente): ?string
    {
        $ente = trim((string) $ente);

        if ($ente === '') {
            return null;
        }

        if (! array_key_exists($ente, self::ENTES)) {
            throw ValidationException::withMessages([
                'ente' => 'Ese ente no es de los del edificio.',
            ]);
        }

        return $ente;
    }

    private function recorta(?string $texto, int $largo): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : mb_substr($texto, 0, $largo);
    }
}
