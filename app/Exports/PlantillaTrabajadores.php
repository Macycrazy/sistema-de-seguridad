<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * La plantilla en blanco para cargar personal.
 *
 * Trae las columnas exactas que el importador reconoce y el Ente como un desplegable, para que
 * quien la llene no invente valores: normalizar entra por aquí. Se rellena una fila por trabajador
 * y se sube en Trabajadores → Importar.
 *
 * A propósito NO trae filas de ejemplo con datos: el importador cargaría esos ejemplos como
 * personas de verdad si a alguien se le olvida borrarlos. La fila 1 es una instrucción —que el
 * importador salta, porque busca la fila de encabezados— y la 2 son los encabezados.
 */
class PlantillaTrabajadores implements WithEvents
{
    private const ENCABEZADOS = ['Cédula', 'Nombre', 'Apellido', 'Ente', 'Dependencia', 'Piso'];

    private const ANCHOS = ['A' => 14, 'B' => 24, 'C' => 24, 'D' => 16, 'E' => 36, 'F' => 10];

    /** Hasta qué fila se prepara el desplegable del ente. Suficiente para toda la nómina. */
    private const FILAS = 600;

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento) {
                $hoja = $evento->sheet->getDelegate();

                // Fila 1: la instrucción. El importador la salta (no es la de encabezados).
                $hoja->setCellValue('A1', 'PLANTILLA · Personal para el registro de accesos. Llena una fila por trabajador y súbela en Trabajadores → Importar. No borres ni muevas la fila de encabezados (la 2).');
                $hoja->mergeCells('A1:F1');
                $hoja->getStyle('A1')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $hoja->getRowDimension(1)->setRowHeight(38);
                $hoja->getStyle('A1')->getFont()->setItalic(true)->getColor()->setRGB('44506B');

                // Fila 2: los encabezados, en el azul del CIIP.
                $hoja->fromArray(self::ENCABEZADOS, null, 'A2');
                $cabecera = $hoja->getStyle('A2:F2');
                $cabecera->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $cabecera->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('004090');
                $cabecera->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $hoja->getRowDimension(2)->setRowHeight(22);
                $hoja->freezePane('A3');

                foreach (self::ANCHOS as $columna => $ancho) {
                    $hoja->getColumnDimension($columna)->setWidth($ancho);
                }

                // El Ente (columna D) como desplegable: CIIP / Marca País / VENAPP, o vacío.
                for ($fila = 3; $fila <= self::FILAS; $fila++) {
                    $validacion = $hoja->getCell("D{$fila}")->getDataValidation();
                    $validacion->setType(DataValidation::TYPE_LIST)
                        ->setErrorStyle(DataValidation::STYLE_INFORMATION)
                        ->setAllowBlank(true)
                        ->setShowInputMessage(true)
                        ->setShowErrorMessage(true)
                        ->setShowDropDown(true)
                        ->setPromptTitle('Ente')
                        ->setPrompt('Elige uno, o déjalo en blanco.')
                        ->setFormula1('"CIIP,Marca País,VENAPP"');
                }
            },
        ];
    }
}
