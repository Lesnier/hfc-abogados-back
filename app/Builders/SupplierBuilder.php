<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

/**
 * Builder personalizado para el modelo Supplier.
 *
 * Intercepta las llamadas where() y califica automáticamente las columnas
 * ambiguas con el prefijo 'suppliers.' antes de que lleguen al Query Builder
 * de MySQL. Esto resuelve el error:
 * "Column 'name' in where clause is ambiguous"
 * que ocurre cuando Voyager/Select2 genera WHERE name LIKE ... mientras el
 * scopeAccess tiene JOINs activos con companies y law_firms.
 */
class SupplierBuilder extends Builder
{
    /**
     * Columnas que existen en múltiples tablas del JOIN y necesitan calificarse.
     */
    protected array $ambiguousColumns = [
        'name',
        'identification',
        'user_id',
        'company_id',
        'approval_status',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Sobreescribe where() para calificar columnas ambiguas automáticamente.
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column) && !str_contains($column, '.') && in_array($column, $this->ambiguousColumns)) {
            $column = 'suppliers.' . $column;
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Sobreescribe orWhere() por consistencia.
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if (is_string($column) && !str_contains($column, '.') && in_array($column, $this->ambiguousColumns)) {
            $column = 'suppliers.' . $column;
        }

        return parent::orWhere($column, $operator, $value);
    }
}
