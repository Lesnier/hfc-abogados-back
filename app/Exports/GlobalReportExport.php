<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Database\Eloquent\Builder;

class GlobalReportExport implements WithMultipleSheets
{
    protected $query;
    protected $filters;

    public function __construct(Builder $query, $filters = [])
    {
        $this->query = $query;
        $this->filters = $filters;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Employees Data
        // We clone the query to avoid modifying the original instance for other sheets
        $sheets[] = new EmployeesSheet(clone $this->query);

        // Sheet 2: Suppliers List
        $sheets[] = new SuppliersSheet(clone $this->query);

        return $sheets;
    }
}
