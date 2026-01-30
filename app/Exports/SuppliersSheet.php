<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use App\Models\Supplier;

class SuppliersSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        // Extract distinct supplier IDs from the filtered employee query
        // This ensures the supplier list matches the employees shown
        $supplierIds = $this->query->pluck('supplier_id')->unique();

        return Supplier::with('company')->whereIn('id', $supplierIds)
            ->get()
            ->map(function($supplier) {
                return [
                    'Empresa' => $supplier->company->name ?? '',
                    'Proveedor' => $supplier->name . ' - ' . $supplier->cuit // Format name - cuit
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Empresa',
            'Proveedor'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '1F497D'], // Dark Blue
            ],
        ]);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    public function title(): string
    {
        return 'Proveedores';
    }
}
