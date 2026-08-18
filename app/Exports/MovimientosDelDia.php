<?php

namespace App\Exports;

use App\Services\Registro\Movimiento;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * El reporte por escrito del día, que es lo que sustituye a la hoja de cálculo.
 *
 * Recibe la colección ya filtrada en vez de una consulta: así exporta exactamente lo que
 * el usuario tiene en pantalla, y de paso funciona sin base de datos mientras el esquema
 * no esté acordado.
 */
class MovimientosDelDia implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    /** @param  Collection<int, Movimiento>  $movimientos */
    public function __construct(private Collection $movimientos) {}

    public function collection(): Collection
    {
        return $this->movimientos->map(fn (Movimiento $m) => [
            $m->fecha(),
            $m->hora(),
            $m->persona->documento(),
            $m->persona->apellidos,
            $m->persona->nombres,
            $m->persona->ente?->etiqueta() ?? '',
            $m->persona->dependencia ?? '',
            $m->persona->tipo->etiqueta(),
            $m->sentido->etiqueta(),
            $m->tieneVehiculo() ? $m->vehiculo->descripcion() : 'A pie',
            $m->registradoPor,
        ]);
    }

    /**
     * Apellidos y nombres van separados, como en el listado de personal: así el reporte
     * se puede ordenar por apellido sin pelearse con la hoja de cálculo.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Fecha', 'Hora', 'Documento', 'Apellidos', 'Nombres', 'Ente',
            'Dependencia', 'Tipo', 'Movimiento', 'Vehículo', 'Registrado por',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $hoja): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
