<?php

namespace App\Livewire\Estacionamiento;

use App\Models\Puesto;
use App\Models\VehiculoDeFlota;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use App\Services\Estacionamiento\Flota;
use App\Services\Estacionamiento\VehiculosFijos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * El estacionamiento visto desde el portón: qué vehículos hay dentro ahora.
 *
 * Es para el guardia, así que no pide permiso propio —como la pantalla de marcar—: cualquiera con
 * sesión la ve. Solo lee lo que el marcaje ya guarda; no marca nada. Un «Actualizar» explícito
 * recalcula, sin sondeo automático.
 */
class Panel extends Component
{
    /** Buscar un vehículo por su placa: «¿está el carro ABC123?», «¿de quién es este que estorba?». */
    public string $busqueda = '';

    /** Si se muestra el registro del día (entradas y salidas), aparte de lo que hay dentro. */
    public bool $verHistorial = false;

    /** Si se muestra el reporte de pernoctas por noche (histórico). Plegado. */
    public bool $verReporte = false;

    /** La noche que se consulta en el reporte (Y-m-d). Empieza en anoche. */
    public string $fechaReporte = '';

    /** Lo que se dice tras asignar un puesto a un vehículo. */
    public string $aviso = '';

    /** El formulario para anotar un vehículo fijo (empresa / que ya estaba). Empieza cerrado. */
    public bool $agregandoFijo = false;

    public string $placaFija = '';

    public string $tipoFija = DatosVehiculo::CARRO;

    public string $marcaFija = '';

    public string $colorFija = '';

    public string $notaFija = '';

    public string $puestoFijo = '';

    /** Vehículo de la flota elegido para anotar (su id), o vacío para teclear a mano. */
    public string $flotaFija = '';

    /** Conductor de entrada: una persona del sistema (cédula) o un nombre suelto. Opcional. */
    public string $conductorCedulaFija = '';

    public string $conductorNombreFija = '';

    /** La salida de un fijo: a quién se le está marcando y quién se lo lleva. */
    public ?int $sacandoFijo = null;

    public string $conductorSalidaCedula = '';

    public string $conductorSalidaNombre = '';

    /** El catálogo de la flota: si se está gestionando y los campos del alta. */
    public bool $gestionandoFlota = false;

    public string $placaFlota = '';

    public string $tipoFlota = DatosVehiculo::CARRO;

    public string $marcaFlota = '';

    public string $colorFlota = '';

    public string $notaFlota = '';

    public function mount(): void
    {
        // Por omisión, el reporte mira la noche de anoche: la que casi siempre se quiere revisar.
        $this->fechaReporte = CarbonImmutable::yesterday()->format('Y-m-d');
    }

    /** Todo lo que hay dentro ahora. Se calcula una vez por render y de aquí sale lo demás. */
    #[Computed]
    public function dentro(): Collection
    {
        return app(Estacionamiento::class)->vehiculosDentro();
    }

    /** Lo que se muestra: todo, o solo lo que coincide con la placa buscada. */
    #[Computed]
    public function vehiculos(): Collection
    {
        $aguja = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->busqueda) ?? '');

        if ($aguja === '') {
            return $this->dentro();
        }

        return $this->dentro()
            ->filter(fn ($v) => str_contains(mb_strtoupper((string) $v->placa), $aguja))
            ->values();
    }

    /**
     * El cupo por bucket: cuántos dentro, el aforo, cuántos libres y si está lleno. Los conteos
     * salen de lo que hay dentro (sin filtrar por la búsqueda).
     *
     * @return array{total:array, carro:array, moto:array}
     */
    #[Computed]
    public function resumen(): array
    {
        $aforos = app(Estacionamiento::class)->aforos();
        $carro = $this->dentro()->where('tipo_vehiculo', DatosVehiculo::CARRO)->count();
        $moto = $this->dentro()->where('tipo_vehiculo', DatosVehiculo::MOTO)->count();

        return [
            'total' => $this->cupo($carro + $moto, $aforos['total']),
            'carro' => $this->cupo($carro, $aforos['carro']),
            'moto' => $this->cupo($moto, $aforos['moto']),
        ];
    }

    /** El registro de vehículos del día: entradas y salidas. Solo si se pidió verlo. */
    #[Computed]
    public function historial(): Collection
    {
        return app(Estacionamiento::class)->delDia(CarbonImmutable::today());
    }

    /** Los que pernoctan: siguen dentro y entraron antes de hoy. */
    #[Computed]
    public function pernoctan(): Collection
    {
        return app(Estacionamiento::class)->pernoctan();
    }

    /**
     * Las opciones de puesto para cada vehículo dentro, listas para el desplegable de su fila:
     * «Sin asignar», su puesto actual (si tiene) y las plazas libres que admiten su tipo. Se arma
     * una sola vez por render y no una consulta por fila.
     *
     * @return array<int, array<string, string>>
     */
    #[Computed]
    public function opcionesPorVehiculo(): array
    {
        $ocupados = app(Estacionamiento::class)->puestosOcupados()->all();

        $libres = Puesto::query()
            ->where('activo', true)
            ->when($ocupados !== [], fn ($q) => $q->whereNotIn('id', $ocupados))
            ->orderBy('orden')->orderBy('codigo')
            ->get();

        $etiqueta = fn (Puesto $p) => $p->codigo.($p->zona ? ' · '.$p->zona : '').' ('.$p->etiquetaTipo().')';
        $mapa = [];

        foreach ($this->dentro() as $vehiculo) {
            $opciones = ['' => 'Sin asignar'];

            // Su plaza actual va primero, para que se vea seleccionada (está ocupada, así que no
            // sale entre las libres).
            if ($vehiculo->puesto_id) {
                $opciones[$vehiculo->puesto_id] = ($vehiculo->puesto ?? '—').' (actual)';
            }

            foreach ($libres as $puesto) {
                if ($puesto->admite($vehiculo->tipo_vehiculo)) {
                    $opciones[$puesto->id] = $etiqueta($puesto);
                }
            }

            $mapa[$vehiculo->persona_id] = $opciones;
        }

        return $mapa;
    }

    /** Si hay algún puesto en el catálogo, para saber si mostrar la columna de asignación. */
    #[Computed]
    public function hayPuestos(): bool
    {
        return Puesto::query()->where('activo', true)->exists();
    }

    /** Asigna, cambia o quita el puesto de un vehículo que está dentro. */
    public function asignarPuesto(int $personaId, string $puestoId): void
    {
        try {
            app(Estacionamiento::class)->asignarPuesto($personaId, $puestoId === '' ? null : (int) $puestoId);
        } catch (ValidationException $e) {
            $this->aviso = $e->validator->errors()->first();

            return;
        }

        $this->aviso = 'Puesto actualizado.';
        $this->actualizar();
    }

    /** Los vehículos fijos anotados que siguen dentro. */
    #[Computed]
    public function fijos(): Collection
    {
        return app(VehiculosFijos::class)->abiertos();
    }

    /** El reporte de la noche elegida: quién pernoctó esa noche (histórico). */
    #[Computed]
    public function reporteNoche(): Collection
    {
        $fecha = $this->fechaReporte !== ''
            ? CarbonImmutable::parse($this->fechaReporte)
            : CarbonImmutable::yesterday();

        return app(Estacionamiento::class)->pernoctaronLaNoche($fecha);
    }

    public function updatedFechaReporte(): void
    {
        unset($this->reporteNoche);
    }

    /** Los puestos libres que admiten el tipo elegido en el formulario de fijo. */
    #[Computed]
    public function puestosLibresFijo(): Collection
    {
        return app(Estacionamiento::class)->puestosLibres($this->tipoFija);
    }

    /** La flota de la empresa que se puede anotar ahora (activa y no está ya dentro). */
    #[Computed]
    public function flotaDisponible(): Collection
    {
        return app(Flota::class)->disponibles();
    }

    /** Todo el catálogo de la flota, para gestionarla. */
    #[Computed]
    public function flota(): Collection
    {
        return app(Flota::class)->todos();
    }

    public function abrirFijo(): void
    {
        $this->reset(
            'placaFija', 'tipoFija', 'marcaFija', 'colorFija', 'notaFija', 'puestoFijo',
            'flotaFija', 'conductorCedulaFija', 'conductorNombreFija', 'aviso',
        );
        $this->resetValidation();
        $this->agregandoFijo = true;
    }

    public function cancelarFijo(): void
    {
        $this->reset(
            'placaFija', 'tipoFija', 'marcaFija', 'colorFija', 'notaFija', 'puestoFijo',
            'flotaFija', 'conductorCedulaFija', 'conductorNombreFija',
        );
        $this->resetValidation();
        $this->agregandoFijo = false;
    }

    /** Al elegir un vehículo de la flota, se toma su tipo (y se limpia el puesto, que depende del tipo). */
    public function updatedFlotaFija(): void
    {
        $vehiculo = $this->flotaFija !== '' ? VehiculoDeFlota::find($this->flotaFija) : null;

        if ($vehiculo) {
            $this->tipoFija = $vehiculo->tipo_vehiculo;
        }

        $this->puestoFijo = '';
        unset($this->puestosLibresFijo);
    }

    /** Al cambiar el tipo, el puesto elegido puede dejar de valer: se limpia y se recalculan libres. */
    public function updatedTipoFija(): void
    {
        $this->puestoFijo = '';
        unset($this->puestosLibresFijo);
    }

    public function agregarFijo(): void
    {
        $this->resetValidation();

        // De la flota (si se eligió) salen placa y tipo; si no, se teclean.
        $deFlota = $this->flotaFija !== '' ? VehiculoDeFlota::find($this->flotaFija) : null;

        try {
            app(VehiculosFijos::class)->registrar(
                placa: $deFlota?->placa ?? $this->placaFija,
                tipoVehiculo: $deFlota?->tipo_vehiculo ?? $this->tipoFija,
                puestoId: $this->puestoFijo === '' ? null : (int) $this->puestoFijo,
                marca: $deFlota?->marca ?? $this->marcaFija,
                color: $deFlota?->color ?? $this->colorFija,
                nota: $this->notaFija ?: $deFlota?->nota,
                usuarioId: auth()->id(),
                conductorCedula: $this->conductorCedulaFija,
                conductorNombre: $this->conductorNombreFija,
                flotaId: $deFlota?->id,
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->cancelarFijo();
        $this->aviso = 'Vehículo anotado en su puesto.';
        $this->actualizar();
    }

    /** Abre la salida de un fijo: pide quién se lo lleva. */
    public function abrirSalida(int $id): void
    {
        $this->reset('conductorSalidaCedula', 'conductorSalidaNombre', 'aviso');
        $this->resetValidation();
        $this->sacandoFijo = $id;
    }

    public function cancelarSalida(): void
    {
        $this->reset('sacandoFijo', 'conductorSalidaCedula', 'conductorSalidaNombre');
        $this->resetValidation();
    }

    public function confirmarSalida(): void
    {
        if ($this->sacandoFijo === null) {
            return;
        }

        try {
            app(VehiculosFijos::class)->sacar(
                VehiculoFijo::findOrFail($this->sacandoFijo),
                conductorCedula: $this->conductorSalidaCedula,
                conductorNombre: $this->conductorSalidaNombre,
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->cancelarSalida();
        $this->aviso = 'Vehículo retirado: su puesto queda libre.';
        $this->actualizar();
    }

    // --- La flota de la empresa (catálogo) ---

    public function abrirFlota(): void
    {
        $this->reset('placaFlota', 'tipoFlota', 'marcaFlota', 'colorFlota', 'notaFlota', 'aviso');
        $this->resetValidation();
        $this->gestionandoFlota = true;
    }

    public function cerrarFlota(): void
    {
        $this->reset('placaFlota', 'tipoFlota', 'marcaFlota', 'colorFlota', 'notaFlota');
        $this->resetValidation();
        $this->gestionandoFlota = false;
    }

    public function guardarFlota(): void
    {
        $this->resetValidation();

        try {
            app(Flota::class)->guardar($this->placaFlota, $this->tipoFlota, $this->marcaFlota, $this->colorFlota, $this->notaFlota);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->reset('placaFlota', 'tipoFlota', 'marcaFlota', 'colorFlota', 'notaFlota');
        $this->aviso = 'Vehículo de la flota guardado.';
        unset($this->flota, $this->flotaDisponible);
    }

    public function eliminarFlota(int $id): void
    {
        app(Flota::class)->eliminar(VehiculoDeFlota::findOrFail($id));
        $this->aviso = 'Vehículo quitado de la flota.';
        unset($this->flota, $this->flotaDisponible);
    }

    public function actualizar(): void
    {
        unset(
            $this->dentro, $this->vehiculos, $this->resumen, $this->historial, $this->pernoctan,
            $this->opcionesPorVehiculo, $this->fijos, $this->puestosLibresFijo,
            $this->flota, $this->flotaDisponible,
        );
    }

    public function render()
    {
        return view('livewire.estacionamiento.panel');
    }

    /** @return array{dentro:int, aforo:int, libres:?int, lleno:bool} */
    private function cupo(int $dentro, int $aforo): array
    {
        return [
            'dentro' => $dentro,
            'aforo' => $aforo,
            'libres' => $aforo > 0 ? max(0, $aforo - $dentro) : null,
            'lleno' => $aforo > 0 && $dentro >= $aforo,
        ];
    }
}
