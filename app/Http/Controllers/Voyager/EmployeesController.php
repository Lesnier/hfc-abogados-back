<?php

namespace App\Http\Controllers\Voyager;

use TCG\Voyager\Http\Controllers\VoyagerBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Employee;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Facades\Voyager;

class EmployeesController extends VoyagerBaseController
{
    public function index(Request $request)
    {
        // GET THE SLUG, ex. 'posts', 'pages', etc.
        $slug = $this->getSlug($request);

        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Check permission
        $this->authorize('browse', app($dataType->model_name));

        $getter = $dataType->server_side ? 'paginate' : 'get';

        $search = (object) ['value' => $request->get('s'), 'key' => $request->get('key'), 'filter' => $request->get('filter')];

        $searchNames = [];
        if ($dataType->server_side) {
            $searchNames = $dataType->browseRows->mapWithKeys(function ($row) {
                return [$row['field'] => $row->getTranslatedAttribute('display_name')];
            });
        }

        $orderBy = $request->get('order_by', $dataType->order_column);
        $sortOrder = $request->get('sort_order', $dataType->order_direction);
        $usesSoftDeletes = false;
        $showSoftDeleted = false;

        // Next Get or Paginate the actual content from the MODEL that corresponds to the slug DataType
        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);

            $query = $model::select($dataType->name.'.*');

            if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope'.ucfirst($dataType->scope))) {
                $query->{$dataType->scope}();
            }

            // Use withTrashed() if model uses SoftDeletes and if toggle is selected
            if ($model && in_array(SoftDeletes::class, class_uses_recursive($model)) && Auth::user()->can('delete', app($dataType->model_name))) {
                $usesSoftDeletes = true;

                if ($request->get('showSoftDeleted')) {
                    $showSoftDeleted = true;
                    $query = $query->withTrashed();
                }
            }

            // If a column has a relationship associated with it, we do not want to show that field
            $this->removeRelationshipField($dataType, 'browse');

            if ($search->value != '' && $search->key && $search->filter) {
                $search_filter = ($search->filter == 'equals') ? '=' : 'LIKE';
                $search_value = ($search->filter == 'equals') ? $search->value : '%'.$search->value.'%';

                $searchField = $dataType->name.'.'.$search->key;
                
                // Find the relationship dynamically regardless of whether the user selected the column or the relationship field
                $row = $dataType->rows->where('type', 'relationship')->filter(function ($item) use ($search) {
                    return $item->details->column == $search->key || $item->field == $search->key;
                })->first();

                if ($row) {
                    $query->whereIn(
                        $dataType->name.'.'.$row->details->column,
                        $row->details->model::where($row->details->label, $search_filter, $search_value)->pluck('id')->toArray()
                    );
                } else {
                    if ($dataType->browseRows->pluck('field')->contains($search->key)) {
                        $query->where($searchField, $search_filter, $search_value);
                    }
                }
            }

            $row = $dataType->rows->where('field', $orderBy)->firstWhere('type', 'relationship');
            if ($orderBy && (in_array($orderBy, $dataType->fields()) || !empty($row))) {
                $querySortOrder = (!empty($sortOrder)) ? $sortOrder : 'desc';
                if (!empty($row)) {
                    $query->select([
                        $dataType->name.'.*',
                        'joined.'.$row->details->label.' as '.$orderBy,
                    ])->leftJoin(
                        $row->details->table.' as joined',
                        $dataType->name.'.'.$row->details->column,
                        'joined.'.$row->details->key
                    );
                }

                $dataTypeContent = call_user_func([
                    $query->orderBy($orderBy, $querySortOrder),
                    $getter,
                ]);
            } elseif ($model->timestamps) {
                $dataTypeContent = call_user_func([$query->latest($model::CREATED_AT), $getter]);
            } else {
                $dataTypeContent = call_user_func([$query->orderBy($model->getKeyName(), 'DESC'), $getter]);
            }

            // Replace relationships' keys for labels and create READ links if a slug is provided.
            $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType);
        } else {
            // If Model doesn't exist, get data from table name
            $dataTypeContent = call_user_func([DB::table($dataType->name), $getter]);
            $model = false;
        }

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($model);

        // Eagerload Relations
        $this->eagerLoadRelations($dataTypeContent, $dataType, 'browse', $isModelTranslatable);

        // Check if server side pagination is enabled
        $isServerSide = isset($dataType->server_side) && $dataType->server_side;

        // Check if a default search key is set
        $defaultSearchKey = $dataType->default_search_key ?? null;

        // Actions
        $actions = [];
        if (!empty($dataTypeContent->first())) {
            foreach (Voyager::actions() as $action) {
                $action = new $action($dataType, $dataTypeContent->first());

                if ($action->shouldActionDisplayOnDataType()) {
                    $actions[] = $action;
                }
            }
        }

        // Define showCheckboxColumn
        $showCheckboxColumn = false;
        if (Auth::user()->can('delete', app($dataType->model_name))) {
            $showCheckboxColumn = true;
        } else {
            foreach ($actions as $action) {
                if (method_exists($action, 'massAction')) {
                    $showCheckboxColumn = true;
                }
            }
        }

        // Define orderColumn
        $orderColumn = [];
        if ($orderBy) {
            $index = $dataType->browseRows->where('field', $orderBy)->keys()->first() + ($showCheckboxColumn ? 1 : 0);
            $orderColumn = [[$index, $sortOrder ?? 'desc']];
        }

        // Define list of columns that can be sorted server side
        $sortableColumns = $this->getSortableColumns($dataType->browseRows);

        $view = 'voyager::bread.browse';

        if (view()->exists("voyager::$slug.browse")) {
            $view = "voyager::$slug.browse";
        }

        // Suppliers for bulk edit modal
        $suppliers = \App\Models\Supplier::orderBy('name')->get();

        return Voyager::view($view, compact(
            'actions',
            'dataType',
            'dataTypeContent',
            'isModelTranslatable',
            'search',
            'orderBy',
            'orderColumn',
            'sortableColumns',
            'sortOrder',
            'searchNames',
            'isServerSide',
            'defaultSearchKey',
            'usesSoftDeletes',
            'showSoftDeleted',
            'showCheckboxColumn',
            'suppliers'
        ));
    }

    public function bulkUpdate(Request $request)
    {
        $ids = explode(',', $request->ids);
        if (empty($ids)) {
            return redirect()->back()->with([
                'message'    => "No se seleccionaron empleados.",
                'alert-type' => 'error',
            ]);
        }

        $fields = [
            'supplier_id', 'condition', 'validity_from', 'validity_to', 
            'suitable_income', 'responsible', 'cost_center', 'approval_status'
        ];

        $updateData = [];
        foreach ($fields as $field) {
            if ($request->filled($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        // Handle Salary Receipt (File)
        if ($request->hasFile('salary_receipt')) {
            $file = $request->file('salary_receipt');
            $path = $file->store('employees', config('voyager.storage.disk'));
            
            // Voyager expected JSON format for files
            $fileData = [
                [
                    'download_link' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]
            ];
            $updateData['salary_receipt'] = json_encode($fileData);
        }

        if (empty($updateData)) {
            return redirect()->back()->with([
                'message'    => "No se ingresaron cambios para actualizar.",
                'alert-type' => 'info',
            ]);
        }

        Employee::whereIn('id', $ids)->update($updateData);

        return redirect()->back()->with([
            'message'    => "Se han actualizado " . count($ids) . " empleados correctamente.",
            'alert-type' => 'success',
        ]);
    }

    public function update(Request $request, $id)
    {
        Log::emergency("CUSTOM EMPLOYEES CONTROLLER HIT for ID: " . $id);
        
        // Call Parent Update Logic
        $response = parent::update($request, $id);

        // Custom Notification Logic (Always runs on submit)
        if ($request->has('notify_supplier') && $request->input('notify_supplier') == '1') {
            Log::info("EmployeesController: 'notify_supplier' checkbox detected for ID: " . $id);
            
            $employee = Employee::find($id);
            if ($employee) {
                // Determine logic
                $supplier = $employee->supplier;
                
                if ($supplier) {
                     // Assuming Supplier -> User relationship via user_id
                     $user = \App\Models\User::find($supplier->user_id);
                     if ($user && $user->email) {
                        $latestVersion = $employee->docVersions()->orderBy('version_number', 'desc')->first();
                        
                        // If no version, try to use current files (fallback to V1 logic if needed, but observer does creating)
                        // Actually, if user just clicked save without version logic, maybe no version exists yet?
                        // Assuming version logic exists or falls back to 'current state' if we wanted.
                        // For now, consistent with Observer: needs version.
                        
                        if ($latestVersion) {
                            try {
                                \Illuminate\Support\Facades\Mail::to($user->email)->send(
                                    new \App\Mail\DocumentStatusMail($employee, $latestVersion, $latestVersion->files)
                                );
                                Log::info("EmployeesController: Email sent to: " . $user->email);
                                
                                // Optional: Flash message
                                // Session::flash('message', 'Correo de notificación enviado.'); 
                                // But response is already redirect.
                                
                            } catch (\Exception $e) {
                                Log::error("EmployeesController: Email error: " . $e->getMessage());
                            }
                        } else {
                             Log::warning("EmployeesController: No document version to notify about.");
                        }
                     } else {
                         Log::warning("EmployeesController: Supplier User has no email.");
                     }
                } else {
                    Log::warning("EmployeesController: No Supplier for employee.");
                }
            }
        }

        return $response;
    }
}
