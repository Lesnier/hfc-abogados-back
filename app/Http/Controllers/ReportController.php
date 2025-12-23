<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Access Control check (can also be middleware)
        if (!$user->hasRole(['admin', 'tech_admin', 'lawyer', 'supplier'])) {
            abort(403, 'No tiene permisos para acceder a los reportes.');
        }

        // Initialize Filter Variables
        $companies = collect();
        $suppliers = collect();
        $employees = collect();

        // --- Logic by Role --- //

        // 1. Admin / Tech Admin
        if ($user->hasRole('admin') || $user->hasRole('tech_admin')) {
            $companies = Company::orderBy('name')->get();
            // Load all or restricted? Let's load empty and let AJAX handle it, or load top 10?
            // User requested: "mostrar todo" for admin.
            // But for performance, maybe just companies first. Let's load all for now as per "can show everything".
            $suppliers = Supplier::orderBy('name')->get();
            $employees = Employee::select('id', 'name', 'identification')->orderBy('name')->get();
        } 
        
        // 2. Lawyer
        elseif ($user->hasRole('lawyer')) {
            // Companies belonging to their Law Firm
            $companies = Company::where('law_firm_id', $user->law_firm_id)->orderBy('name')->get();
            
            // Suppliers belonging to companies of their Law Firm
            // We can pre-load all of them or leave empty to force selection.
            // Let's pre-load available suppliers for usability.
            $companyIds = $companies->pluck('id');
            $suppliers = Supplier::whereIn('company_id', $companyIds)->orderBy('name')->get();
            
            // Employees belonging to those suppliers
            $supplierIds = $suppliers->pluck('id');
            $employees = Employee::whereIn('supplier_id', $supplierIds)->select('id', 'name', 'identification')->orderBy('name')->get();
        }

        // 3. Supplier
        elseif ($user->hasRole('supplier')) {
            // Get the Supplier profile linked to this user
            // Assuming 1-to-1 relationship or finding first.
            $supplierProfile = Supplier::where('user_id', $user->id)->first();

            if ($supplierProfile) {
                // Fixed Company (ReadOnly in UI ideally)
                $companies = Company::where('id', $supplierProfile->company_id)->get();
                
                // Fixed Supplier (ReadOnly in UI ideally)
                $suppliers = collect([$supplierProfile]);

                // Employees of this supplier
                $employees = Employee::where('supplier_id', $supplierProfile->id)->select('id', 'name', 'identification')->orderBy('name')->get();
            }
        }

        return view('vendor.voyager.reports.index', compact('companies', 'suppliers', 'employees'));
    }

    public function getFilters(Request $request)
    {
        $user = auth()->user();
        
        // Start building queries
        $suppliersQuery = Supplier::query()->orderBy('name');
        $employeesQuery = Employee::query()->select('id', 'name', 'identification')->orderBy('name');

        // Apply Access Scope First
        if ($user->hasRole('lawyer')) {
             $myCompanyIds = Company::where('law_firm_id', $user->law_firm_id)->pluck('id');
             $suppliersQuery->whereIn('company_id', $myCompanyIds);
             // Employees check is implicit via supplier check below, but safer to enforce
             $mySupplierIds = Supplier::whereIn('company_id', $myCompanyIds)->pluck('id');
             $employeesQuery->whereIn('supplier_id', $mySupplierIds);
        } elseif ($user->hasRole('supplier')) {
             $supplierProfile = Supplier::where('user_id', $user->id)->first();
             if ($supplierProfile) {
                 $suppliersQuery->where('id', $supplierProfile->id);
                 $employeesQuery->where('supplier_id', $supplierProfile->id);
             } else {
                 // Should not happen, but return empty
                 return response()->json(['suppliers' => [], 'employees' => []]);
             }
        }

        // Apply Cascading Filters
        if ($request->company_id) {
            $suppliersQuery->where('company_id', $request->company_id);
            // If company selected, limit employees to suppliers of that company
            $companySupplierIds = Supplier::where('company_id', $request->company_id)->pluck('id');
            $employeesQuery->whereIn('supplier_id', $companySupplierIds);
        }

        if ($request->supplier_id) {
            // Users for this supplier
            $employeesQuery->where('supplier_id', $request->supplier_id);
        }
        
        // Execution
        // Limit to 50 for performance? User mentioned paginate or 10.
        // Let's return all for now unless it's huge. Select2 handles searching on client side if data is present.
        // If server-side searching is needed (ajax: { url: ... }), that's different.
        // Given "mode cascada", typically we update the OPTIONS.
        $suppliers = $suppliersQuery->get(['id', 'name', 'identification']);
        $employees = $employeesQuery->get();

        return response()->json([
            'suppliers' => $suppliers,
            'employees' => $employees,
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : null;
        $endDate   = $request->end_date   ? Carbon::parse($request->end_date) : null;

        // Build the query hierarchy
        $companies = Company::access()
            ->when($request->company_id, function($q) use ($request) {
                return $q->where('companies.id', $request->company_id);
            })
            ->with(['suppliers' => function($q) use ($request, $startDate, $endDate) {
                $q->access();
                if ($request->supplier_id) {
                    $q->where('suppliers.id', $request->supplier_id);
                }
                
                // Eager load employees with filters
                $q->with(['employees' => function($q) use ($request, $startDate, $endDate) {
                    $q->access();
                    if ($request->employee_id) {
                        $q->where('employees.id', $request->employee_id);
                    }
                    if ($startDate) {
                        $q->whereDate('employees.created_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('employees.created_at', '<=', $endDate);
                    }
                    
                    // New Filters
                    if ($request->filled('approval_status')) {
                        $q->where('employees.approval_status', $request->approval_status);
                    }
                    
                    if ($request->filled('enabled')) {
                        $now = Carbon::now()->toDateString();
                        if ($request->enabled == '1') {
                            $q->whereDate('employees.validity_from', '<=', $now)
                              ->whereDate('employees.validity_to', '>=', $now);
                        } elseif ($request->enabled == '0') {
                            $q->where(function($query) use ($now) {
                                $query->whereDate('employees.validity_from', '>', $now)
                                      ->orWhereDate('employees.validity_to', '<', $now);
                            });
                        }
                    }

                    if ($request->filled('cost_center')) {
                        $q->where('employees.cost_center', 'like', '%' . $request->cost_center . '%');
                    }

                    if ($request->filled('responsible')) {
                        $q->where('employees.responsible', 'like', '%' . $request->responsible . '%');
                    }
                }]);
            }])
            ->get();

        // Calculate Global KPIs or prepare data structure
        // We can do this in the view or here. 
        // Let's pass the hierarchy to the view.

        $pdf = Pdf::loadView('vendor.voyager.reports.pdf', [
            'companies' => $companies,
            'start_date' => $startDate ? $startDate->format('d/m/Y') : 'Inicio',
            'end_date'   => $endDate   ? $endDate->format('d/m/Y')   : 'Actualidad',
            'filters'    => $request->all()
        ]);

        return $pdf->download('reporte_'.date('Ymd_His').'.pdf');
    }
}
