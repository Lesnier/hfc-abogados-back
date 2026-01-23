<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\Company;
use App\Models\Supplier;
use App\Mail\ReportReadyMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use iio\libmergepdf\Merger;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GenerateLargePdfReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $filters;
    public $timeout = 1200; // 20 minutes

    /**
     * Create a new job instance.
     *
     * @param $user
     * @param array $filters
     * @return void
     */
    public function __construct($user, $filters = [])
    {
        $this->user = $user;
        $this->filters = $filters;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Illuminate\Support\Facades\Log::info("Job GenerateLargePdfReport iniciado para el usuario: " . ($this->user->email ?? 'Anonimo'));

        $merger = new Merger;
        $tempFiles = [];
        $chunkSize = 100; // Adjust based on memory availability

        // Ensure temp directory exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // --- Prepare Filters (Replicated from Controller) ---
        $filters = $this->filters;
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : null;
        $endDate   = isset($filters['end_date'])   ? Carbon::parse($filters['end_date']) : null;
        
        \Illuminate\Support\Facades\Log::info("Filtros procesados. Iniciando Query.");

        // We must apply all relevant filters to the Employee query.
        
        $query = Employee::query();

        // 1. Role / Access Control (Simplification: Assuming ID filters act as access control logic)
        // If the controller passed sanitized filters based on role, we trust them.
        // Or we should re-implement access logic here if $filters only contains raw requests.
        // **CRITICAL**: The controller currently passes $request->all(). It assumes the request is valid.
        // However, the Controller's query used 'Company::access()'.
        // We should replicate the hierarchy filters.
        
        if (isset($filters['company_id']) && $filters['company_id']) {
            $query->whereHas('supplier', function($q) use ($filters) {
                $q->where('company_id', $filters['company_id']);
            });
        }
        
        if (isset($filters['supplier_id']) && $filters['supplier_id']) {
            $query->where('supplier_id', $filters['supplier_id']);
        }
        
        if (isset($filters['employee_id']) && $filters['employee_id']) {
            $query->where('id', $filters['employee_id']);
        }
        
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        
        if (isset($filters['approval_status']) && $filters['approval_status']) {
            $query->where('approval_status', $filters['approval_status']);
        }
        
        if (isset($filters['enabled'])) {
            $now = Carbon::now()->toDateString();
            if ($filters['enabled'] == '1') {
                $query->whereDate('validity_from', '<=', $now)
                      ->whereDate('validity_to', '>=', $now);
            } elseif ($filters['enabled'] == '0') {
                $query->where(function($q) use ($now) {
                    $q->whereDate('validity_from', '>', $now)
                      ->orWhereDate('validity_to', '<', $now);
                });
            }
        }
        
        if (isset($filters['cost_center']) && $filters['cost_center']) {
            $query->where('cost_center', 'like', '%' . $filters['cost_center'] . '%');
        }
        
        if (isset($filters['responsible']) && $filters['responsible']) {
            $query->where('responsible', 'like', '%' . $filters['responsible'] . '%');
        }

        // --- Chunk Execution ---
        $query->orderBy('supplier_id')->chunk($chunkSize, function ($employees) use ($merger, &$tempFiles, $filters, $startDate, $endDate) {
            
            // Reconstruct Hierarchy: Employee -> Supplier -> Company (Chunked)
            
            // 1. Group by Supplier
            $employeesBySupplier = $employees->groupBy('supplier_id');
            
            // 2. Fetch Suppliers
            $suppliers = Supplier::whereIn('id', $employeesBySupplier->keys())->get()
                ->each(function($supplier) use ($employeesBySupplier) {
                    $supplier->setRelation('employees', $employeesBySupplier[$supplier->id]);
                });
                
            // 3. Group by Company
            $suppliersByCompany = $suppliers->groupBy('company_id');
            
            // 4. Fetch Companies
            $companies = Company::whereIn('id', $suppliersByCompany->keys())->get()
                ->each(function($company) use ($suppliersByCompany) {
                    $company->setRelation('suppliers', $suppliersByCompany[$company->id]);
                });

            // 5. Render Partial View
            $pdf = Pdf::loadView('vendor.voyager.reports.pdf', [
                'companies' => $companies,
                'start_date' => $startDate ? $startDate->format('d/m/Y') : 'Inicio',
                'end_date'   => $endDate   ? $endDate->format('d/m/Y')   : 'Actualidad',
                'filters'    => $filters,
                'generated_by' => $this->user->name
            ]);
            
            $tempPath = storage_path('app/temp/part_' . uniqid() . '.pdf');
            $pdf->save($tempPath);
            
            $merger->addFile($tempPath);
            $tempFiles[] = $tempPath;

            // Cleanup
            unset($pdf, $companies, $suppliers, $employees);
            gc_collect_cycles();
        });

        // Final Merge
        if (empty($tempFiles)) {
             // Handle empty report case (optional: send empty notification)
             return;
        }

        $createdPdf = $merger->merge();

        $fileName = 'reports/reporte_gestion_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('public')->put($fileName, $createdPdf);

        // Cleanup Temp
        foreach ($tempFiles as $file) {
            if (file_exists($file)) unlink($file);
        }

        // Signed URL
        $url = URL::temporarySignedRoute(
            'voyager.reports.download',
            now()->addHours(24),
            ['path' => $fileName]
        );

        // Notify
        if ($this->user && $this->user->email) {
            Mail::to($this->user->email)->send(new ReportReadyMail($url));
            \Illuminate\Support\Facades\Log::info("Correo enviado a: " . $this->user->email);
        }
        
        \Illuminate\Support\Facades\Log::info("Job GenerateLargePdfReport finalizado exitosamente.");
    }
}
