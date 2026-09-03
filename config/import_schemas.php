<?php

use App\Models\Company;
use App\Models\Supplier;
use App\Models\Employee;

/*
|--------------------------------------------------------------------------
| Esquemas del importador estandarizado
|--------------------------------------------------------------------------
|
| Cada entrada describe una hoja del archivo "plantilla_importacion_
| estandarizada.xlsx": a qué modelo/tabla corresponde, cuál es su clave
| de negocio única, qué columnas trae, y qué columnas son referencias a
| otra entidad (fk) resueltas por external_id o por su clave de negocio.
|
| 'order' define el orden de dependencia en el que se procesan las hojas
| durante la ejecución (los padres antes que los hijos).
|
*/

return [

    'companias' => [
        'label' => 'Compañías',
        'sheet' => 'companias',
        'order' => 1,
        'mode' => 'entity',
        'model' => Company::class,
        'unique_key' => 'identification',
        'columns' => [
            'external_id' => ['type' => 'text', 'required' => false],
            'identification' => ['type' => 'text', 'required' => true],
            'name' => ['type' => 'text', 'required' => true],
            'country' => ['type' => 'text', 'required' => false],
            'phone' => ['type' => 'text', 'required' => false],
            'email' => ['type' => 'text', 'required' => false],
        ],
        'fk' => [],
        // law_firm_name: coincidencia de texto exacto contra law_firms.name (no es un patrón external_id/identification)
        'text_match' => [
            'law_firm_name' => [
                'local_field' => 'law_firm_id',
                'model' => \App\Models\LawFirm::class,
                'match_field' => 'name',
                'label' => 'Estudio de Abogados',
            ],
        ],
    ],

    'proveedores' => [
        'label' => 'Proveedores',
        'sheet' => 'proveedores',
        'order' => 2,
        'mode' => 'entity',
        'model' => Supplier::class,
        'unique_key' => 'identification',
        'columns' => [
            'external_id' => ['type' => 'text', 'required' => false],
            'identification' => ['type' => 'text', 'required' => true],
            'name' => ['type' => 'text', 'required' => true],
            'complaint_cc' => ['type' => 'text', 'required' => false],
            'risk_end' => ['type' => 'select', 'required' => false, 'options' => ['STD', 'S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'No_Aplica']],
            'cbu_checking_account' => ['type' => 'text', 'required' => false],
            'name_bank' => ['type' => 'text', 'required' => false],
            'number_checking_account' => ['type' => 'text', 'required' => false],
            'approval_status' => ['type' => 'select', 'required' => false, 'options' => ['Revisión', 'Aprobado', 'Rechazado', 'Baja']],
        ],
        'fk' => [
            'company_id' => [
                'parent_entity' => 'companias',
                'external_col' => 'company_external_id',
                'identification_col' => 'company_identification',
                'label' => 'Compañía',
            ],
        ],
        'text_match' => [],
    ],

    'empleados' => [
        'label' => 'Empleados',
        'sheet' => 'empleados',
        'order' => 3,
        'mode' => 'entity',
        'model' => Employee::class,
        'unique_key' => 'identification',
        'columns' => [
            'external_id' => ['type' => 'text', 'required' => false],
            'identification' => ['type' => 'text', 'required' => true],
            'name' => ['type' => 'text', 'required' => true],
            'cuil' => ['type' => 'text', 'required' => false],
            'condition' => ['type' => 'select', 'required' => false, 'options' => ['Empleado', 'Autónomo']],
            'suitable_income' => ['type' => 'number', 'required' => false],
            'responsible' => ['type' => 'text', 'required' => false],
            'approval_status' => ['type' => 'select', 'required' => false, 'options' => ['Revisión', 'Aprobado', 'Rechazado', 'Baja']],
            'cost_center' => ['type' => 'text', 'required' => false],
            'validity_from' => ['type' => 'date', 'required' => false],
            'validity_to' => ['type' => 'date', 'required' => false],
        ],
        'fk' => [
            'supplier_id' => [
                'parent_entity' => 'proveedores',
                'external_col' => 'supplier_external_id',
                'identification_col' => 'supplier_identification',
                'label' => 'Proveedor',
            ],
        ],
        'text_match' => [],
    ],

    'relacion_empleado_proveedor' => [
        'label' => 'Relación Empleado-Proveedor',
        'sheet' => 'relacion_empleado_proveedor',
        'order' => 4,
        'mode' => 'relation',
        'model' => Employee::class,
        'match_entity' => 'empleados',
        'match_external_col' => 'employee_external_id',
        'match_identification_col' => 'employee_identification',
        'fk' => [
            'supplier_id' => [
                'parent_entity' => 'proveedores',
                'external_col' => 'supplier_external_id',
                'identification_col' => 'supplier_identification',
                'label' => 'Proveedor',
            ],
        ],
    ],

];
