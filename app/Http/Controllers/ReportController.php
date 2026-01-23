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

        // Dispatch Job
        \App\Jobs\GenerateLargePdfReport::dispatch(auth()->user(), $request->all());

        return back()->with([
            'message'    => 'El reporte se está generando en segundo plano. Recibirás un correo con el enlace de descarga.',
            'alert-type' => 'info',
        ]);
    }

    public function download(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(401);
        }
        
        $path = $request->query('path');
        
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download($path);
    }
}
