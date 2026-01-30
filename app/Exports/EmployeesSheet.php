<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class EmployeesSheet implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $query;
    protected $rowCount = 0;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        // Ensure we load necessary relationships
        return $this->query->with(['supplier.company']);
    }

    public function headings(): array
    {
        return [
            'Empresa',
            'Proveedor',
            'Fila',
            'Empleado',
            'DNI',
            'CUIL/CUIT',
            'Condición',
            'F931/AFIP',
            'ART/ACC Personales',
            'Vigencia Desde',
            'Vigencia Hasta',
            'Seguro de Vida Oblig'
        ];
    }

    public function map($employee): array
    {
        $this->rowCount++;
        
        return [
            $employee->supplier->company->name ?? '',
            $employee->supplier->name ?? '',
            $this->rowCount, // Simple counter as Fila
            $employee->name, // Fixed: Using 'name' attribute
            $employee->identification, // Fixed: Using 'identification' attribute
            $employee->cuil,
            $employee->condition ?? 'EMPLEADO', // Default or actual field? Assuming field or static
            $employee->f931_afip ? 'SI' : 'NO', // Assuming boolean/int
            $employee->art_acc_personales ? 'SI' : 'NO',
            $employee->validity_from, // Formatting needed? Excel handles dates usually
            $employee->validity_to,
            $employee->life_insurance ? 'SI' : 'NO',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Get the last column (L is the 12th column)
        $lastColumn = 'L';
        
        // Style Header Row (1)
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '1F497D'], // Dark Blue
            ],
        ]);

        // Enable Auto Filter for the entire range of data
        $sheet->setAutoFilter("A1:{$lastColumn}" . ($sheet->getHighestRow()));

        // Autosize columns
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    public function title(): string
    {
        return 'Datos_Empleados';
    }
}
