<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use TCG\Voyager\Facades\Voyager;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;

class CsvImportController extends Controller
{
    protected $slugToModel = [
        'companies' => Company::class,
        'suppliers' => Supplier::class,
        'employees' => Employee::class,
    ];

    public function downloadTemplate($slug)
    {
        if (!array_key_exists($slug, $this->slugToModel)) {
            return abort(404);
        }

        $modelClass = $this->slugToModel[$slug];
        $model = new $modelClass();
        $fillable = $model->getFillable();

        $exampleRow = array_map(function ($field) use ($slug) {
            return $this->buildExampleValue($slug, $field);
        }, $fillable);

        $headerLine = implode(';', $fillable);
        $exampleLine = implode(';', $exampleRow);

        // Return download response
        return response()->streamDownload(function () use ($headerLine, $exampleLine) {
            // BOM so Excel detects UTF-8 (tildes, ñ, etc.) correctly
            echo "\xEF\xBB\xBF";
            echo $headerLine . "\r\n";
            echo $exampleLine . "\r\n";
        }, $slug . '_template.csv');
    }

    /**
     * Builds a helpful example value for a given field: the allowed options
     * for select-type fields (taken from the same rules used to validate the
     * import, so the example always matches what will actually be accepted),
     * or a representative sample value otherwise.
     */
    private function buildExampleValue($slug, $field)
    {
        $rules = $this->getValidationRules($slug);

        if (isset($rules[$field])) {
            foreach (explode('|', $rules[$field]) as $rule) {
                if (Str::startsWith($rule, 'in:')) {
                    $options = explode(',', Str::after($rule, 'in:'));
                    return implode(' / ', $options);
                }
            }
        }

        // Selects not covered by import validation rules: pull options from the BREAD config.
        $dataType = \TCG\Voyager\Models\DataType::where('slug', $slug)->first();
        $dataRow = $dataType ? $dataType->rows->firstWhere('field', $field) : null;
        if ($dataRow && in_array($dataRow->type, ['select_dropdown', 'radio_btn']) && property_exists($dataRow->details, 'options')) {
            return implode(' / ', array_keys((array) $dataRow->details->options));
        }

        $examples = [
            'employees' => [
                'identification' => '20-12345678-6',
                'name' => 'Juan Pérez',
                'cuil' => '20-12345678-6',
                'suitable_income' => '150000',
                'responsible' => 'María Gómez',
                'cost_center' => 'CC-001',
                'validity_from' => '2024-01-15',
                'validity_to' => '2025-01-15',
                'supplier_id' => '1 (ID existente en Proveedores)',
            ],
            'suppliers' => [
                'identification' => '30-71234567-4',
                'name' => 'Proveedor S.A.',
                'complaint_cc' => 'reclamos@proveedor.com',
                'cbu_checking_account' => '0070001600001234567891',
                'name_bank' => 'Banco Nación',
                'number_checking_account' => '000012345678',
                'company_id' => '1 (ID existente en Compañías)',
                'user_id' => '1 (ID existente en Usuarios, rol supplier)',
            ],
            'companies' => [
                'identification' => '30-71234567-4',
                'name' => 'Empresa S.A.',
                'country' => 'Argentina',
                'user_id' => '1 (ID existente en Usuarios, rol company)',
                'logo' => '',
                'phone' => '+54 11 5555-5555',
                'email' => 'contacto@empresa.com',
                'law_firm_id' => '1 (ID existente en Firmas)',
            ],
        ];

        if (isset($examples[$slug][$field])) {
            return $examples[$slug][$field];
        }

        if ($dataRow && in_array($dataRow->type, ['date', 'timestamp'])) {
            return '2024-01-15';
        }

        if ($dataRow && $dataRow->type === 'number') {
            return '1000';
        }

        return 'Ejemplo';
    }

    public function import(Request $request, $slug)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $modelClass = $this->getModelClass($slug);

        if (!$modelClass) {
            return redirect()->back()->with([
                'message'    => "No se encontró el modelo para: $slug",
                'alert-type' => 'error',
            ]);
        }

        // Parse CSV
        $rows = [];
        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ';');
            
            // Clean headers (trim whitespace and remove BOM)
            $headers = array_map(function($header) {
                return trim(preg_replace('/[\xEF\xBB\xBF]/', '', $header));
            }, $headers ?? []);

            if (empty($headers)) {
                 return response()->json([
                    'status' => 'error',
                    'errors' => ['El archivo CSV parece estar vacío o mal formateado.']
                ], 422);
            }

            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                if (count($headers) !== count($data)) {
                    continue; 
                }

                // UTF-8 Conversion
                $data = array_map(function ($value) {
                    return mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1');
                }, $data);

                $rows[] = array_combine($headers, $data);
            }
            fclose($handle);
        }

        // Validate Rows
        $errors = $this->validateRows($slug, $rows);

        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'errors' => $errors
            ], 422);
        }

        // Process Import
        $count = 0;
        foreach ($rows as $row) {
            $model = new $modelClass();
            $fillable = $model->getFillable();
            $cleanRow = \Illuminate\Support\Arr::only($row, $fillable);

            // Handle empty date fields (convert '' to null) to avoid MySQL errors if column is nullable
            foreach ($cleanRow as $key => $value) {
                if ($value === '') {
                    $cleanRow[$key] = null;
                }
            }

            if (isset($cleanRow['identification']) && !empty($cleanRow['identification'])) {
                $modelClass::updateOrCreate(
                    ['identification' => $cleanRow['identification']],
                    $cleanRow
                );
                $count++;
            } else {
                // Fallback catch-all if no identification
                $modelClass::create($cleanRow);
                $count++;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Importación exitosa. $count registros procesados.",
            'alert-type' => 'success',
        ]);
    }

    private function getValidationRules($slug)
    {
        if ($slug === 'employees') {
            return [
                'validity_from' => 'nullable|date_format:Y-m-d',
                'validity_to' => 'nullable|date_format:Y-m-d',
                'approval_status' => 'nullable|in:Revisión,Aprobado,Rechazado,Baja',
                'supplier_id' => 'nullable|exists:suppliers,id',
                'condition' => 'nullable|in:Autónomo,Empleado',
                'cuil' => 'nullable|cuil_ar', // Add CUIL validation
            ];
        }

        if ($slug === 'suppliers') {
            return [
                'approval_status' => 'nullable|in:Revisión,Aprobado,Rechazado,Baja',
                'company_id' => 'nullable|exists:companies,id',
                'user_id' => 'nullable|exists:users,id',
                'sbu_checking_account' => 'nullable|numeric',
                'cbu_checking_account' => 'nullable|numeric|digits:22|cbu_ar', // Use new rule
                'number_checking_account' => 'nullable|numeric',
            ];
        }

        if ($slug === 'companies') {
            return [
                'law_firm_id' => 'nullable|exists:law_firms,id',
                'user_id' => 'nullable|exists:users,id',
            ];
        }

        return [];
    }

    private function validateRows($slug, $rows)
    {
        $errors = [];
        $rules = $this->getValidationRules($slug);

        if (empty($rules)) {
            return [];
        }

        // Custom Messages in Spanish
        $messages = [
            'date_format' => 'El campo :attribute debe tener el formato :format.',
            'in'          => 'El valor seleccionado para :attribute no es válido.',
            'exists'      => 'El valor seleccionado para :attribute no existe en el sistema.',
            'numeric'     => 'El campo :attribute debe ser un número.',
            'required'    => 'El campo :attribute es obligatorio.',
        ];

        // Custom Attributes (quoted)
        $attributes = [];
        foreach (array_keys($rules) as $key) {
            $attributes[$key] = "'$key'";
        }

        // Get allowed columns from model
        $modelClass = $this->getModelClass($slug);
        $model = new $modelClass();
        $allowedColumns = $model->getFillable();
        // Ensure identification is allowed if not in fillable
        if (!in_array('identification', $allowedColumns)) {
            $allowedColumns[] = 'identification';
        }

        foreach ($rows as $index => $row) {
            // Check for unknown columns
            $extraColumns = array_diff(array_keys($row), $allowedColumns);
            if (!empty($extraColumns)) {
                 $errors[] = "Fila " . ($index + 1) . ": Columnas no permitidas encontradas: " . implode(', ', $extraColumns);
                 continue;
            }

            // Clean empty strings to null for validation that expects nulls or specific formats
            $dataToValidate = array_map(function($value) {
                // Trim value
                $value = is_string($value) ? trim($value) : $value;
                return $value === '' ? null : $value;
            }, $row);

            $validator = Validator::make($dataToValidate, $rules, $messages, $attributes);

            $validator->after(function ($validator) use ($slug, $dataToValidate, $index) {
                // Role Validation Logic
                if ($slug === 'companies' && !empty($dataToValidate['user_id'])) {
                    $user = \App\Models\User::find($dataToValidate['user_id']);
                    if ($user && !$user->hasRole('company')) {
                        $validator->errors()->add('user_id', "El usuario seleccionado (ID: {$dataToValidate['user_id']}) no tiene el rol de 'company'.");
                    }
                    
                    // Law Firm Ownership Check for Lawyers
                    $currentUser = auth()->user();
                    if ($currentUser->hasRole('lawyer')) {
                         if (empty($dataToValidate['law_firm_id']) || $dataToValidate['law_firm_id'] != $currentUser->law_firm_id) {
                            $validator->errors()->add('law_firm_id', "Como abogado, solo puede importar compañías asignadas a su firma (ID: {$currentUser->law_firm_id}).");
                         }
                    }
                }

                if ($slug === 'suppliers' && !empty($dataToValidate['user_id'])) {
                    $user = \App\Models\User::find($dataToValidate['user_id']);
                    if ($user && !$user->hasRole('supplier')) {
                        $validator->errors()->add('user_id', "El usuario seleccionado (ID: {$dataToValidate['user_id']}) no tiene el rol de 'supplier'.");
                    }
                }
                
                // Deep Relationship Validation
                $currentUser = auth()->user();

                // 1. Lawyer -> Suppliers: Verify company belongs to Lawyer's Firm
                if ($slug === 'suppliers' && $currentUser->hasRole('lawyer') && !empty($dataToValidate['company_id'])) {
                    $company = \App\Models\Company::find($dataToValidate['company_id']);
                    if ($company && $company->law_firm_id != $currentUser->law_firm_id) {
                         $validator->errors()->add('company_id', "Como abogado, solo puede asignar proveedores a empresas de su firma (ID: {$currentUser->law_firm_id}).");
                    }
                }

                // 2. Lawyer -> Employees: Verify supplier -> company belongs to Lawyer's Firm
                if ($slug === 'employees' && $currentUser->hasRole('lawyer') && !empty($dataToValidate['supplier_id'])) {
                    $supplier = \App\Models\Supplier::find($dataToValidate['supplier_id']);
                    if ($supplier && $supplier->company && $supplier->company->law_firm_id != $currentUser->law_firm_id) {
                        $validator->errors()->add('supplier_id', "El proveedor seleccionado pertenece a una empresa fuera de su firma.");
                    }
                }

                // 3. Supplier -> Employees: Verify supplier belongs to Logged-in Supplier User
                if ($slug === 'employees' && $currentUser->hasRole('supplier') && !empty($dataToValidate['supplier_id'])) {
                    $supplier = \App\Models\Supplier::find($dataToValidate['supplier_id']);
                    // Check if the supplier record is associated with the logged-in user
                    if ($supplier && $supplier->user_id != $currentUser->id) {
                        $validator->errors()->add('supplier_id', "Solo puede importar empleados asociados a su propio registro de proveedor.");
                    }
                }
            });

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "Fila " . ($index + 1) . ": " . $message;
                }
            }
        }

        return $errors;
    }

    private function getModelClass($slug)
    {
        return $this->slugToModel[$slug] ?? null;
    }
}
