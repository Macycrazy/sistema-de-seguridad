<?php

namespace App\Services\Registro;

/**
 * Una persona vista desde el registro.
 *
 * La forma sale del listado de personal real: apellidos y nombres van en columnas
 * separadas, y la dependencia, el piso, el cargo y el ente son cuatro datos distintos.
 *
 * De un invitado se guarda mucho menos: nombre y a quién viene a ver. Nada más.
 */
final readonly class Persona
{
    public function __construct(
        public string $id,
        /** El documento tal cual viene en la fuente. Null cuando no hay ninguno registrado. */
        public ?string $cedula,
        public string $apellidos,
        public string $nombres,
        public TipoDePersona $tipo,
        public ?Ente $ente = null,
        public ?string $dependencia = null,
        public ?string $piso = null,
        public ?string $cargo = null,
        public ?string $visitaA = null,
    ) {}

    /** «PÉREZ GONZÁLEZ, José Rafael» — como se lee en el listado. */
    public function nombreCompleto(): string
    {
        return trim($this->apellidos.', '.$this->nombres);
    }

    /** «José Rafael Pérez González» — como se lee en una pantalla. */
    public function nombre(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    /**
     * El documento, listo para mostrar.
     *
     * No todo el personal tiene cédula venezolana: en el listado real hay pasaportes
     * (RD7368881, FZ350899, N01870456), la serie de naturalizados y gente sin documento
     * registrado. Formatear a ciegas convertía todo eso en «V-0».
     *
     * Ojo: el listado no dice si un documento es V o E, así que aquí no se inventa el
     * prefijo — se guarda ya puesto en el propio dato.
     */
    public function documento(): string
    {
        $cedula = trim((string) $this->cedula);

        if ($cedula === '' || $cedula === '*') {
            return 'Sin documento';
        }

        return $cedula;
    }

    public function tieneDocumento(): bool
    {
        $cedula = trim((string) $this->cedula);

        return $cedula !== '' && $cedula !== '*';
    }

    /** Dependencia y piso si es personal; a quién visita si es invitado. */
    public function adscripcion(): string
    {
        if ($this->tipo === TipoDePersona::Invitado) {
            return $this->visitaA ? 'Visita a '.$this->visitaA : 'Invitado';
        }

        $partes = array_filter([$this->dependencia, $this->piso ? 'Piso '.$this->piso : null]);

        return $partes ? implode(' · ', $partes) : 'Sin dependencia';
    }
}
