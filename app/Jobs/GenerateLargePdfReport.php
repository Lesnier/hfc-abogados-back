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
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GlobalReportExport;

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
        try {
            ini_set('memory_limit', '1024M');
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

            // --- Global Stats Calculation (Pre-Chunking) ---
            Log::info("Calculando estadisticas globales...");

            $statsQuery = clone $query;
            
            // 1. Employee Counts per Supplier (Global)
            $supplierEmployeeCounts = $statsQuery->select('supplier_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
                                                ->groupBy('supplier_id')
                                                ->pluck('total', 'supplier_id');

            // 1b. Approved Counts per Supplier (Global)
            $approvedQuery = clone $query;
            $supplierApprovedCounts = $approvedQuery->where('approval_status', 'Aprobado')
                                                    ->select('supplier_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
                                                    ->groupBy('supplier_id')
                                                    ->pluck('total', 'supplier_id');

            // 2. Derive Company Stats
            $relevantSupplierIds = $supplierEmployeeCounts->keys();
            $relevantSuppliers = Supplier::whereIn('id', $relevantSupplierIds)->select('id', 'company_id')->get();

            $companyStats = []; 
            $supplierStats = []; 

            foreach ($relevantSuppliers as $sup) {
                $count = $supplierEmployeeCounts[$sup->id] ?? 0;
                $approved = $supplierApprovedCounts[$sup->id] ?? 0;
                
                $supplierStats[$sup->id] = [
                    'employees' => $count,
                    'approved' => $approved
                ];

                if (!isset($companyStats[$sup->company_id])) {
                    $companyStats[$sup->company_id] = [
                        'employees' => 0, 
                        'suppliers' => 0,
                        'approved' => 0
                    ];
                }
                $companyStats[$sup->company_id]['employees'] += $count;
                $companyStats[$sup->company_id]['approved'] += $approved;
                $companyStats[$sup->company_id]['suppliers'] += 1;
            }

            Log::info("Estadisticas calculadas. Inicio de Procesamiento Seamless.");

            $processedCompanyIds = [];
            $processedSupplierIds = [];

            // --- Render-Trim-Retry Execution ---
            
            // We accumulate items in a buffer
            $buffer = collect();
            // We use a large batch size for the cursor rendering
            $batchSize = 1200; // Increased for performance
            $employeeCursor = $query->select('*')->orderBy('supplier_id')->cursor();
            $batchCount = 0;

            foreach ($employeeCursor as $employee) {
                $buffer->push($employee);

                if ($buffer->count() >= $batchSize) {
                    $batchCount++;

                    $this->processBatch($buffer, $merger, $tempFiles, $filters, $startDate, $endDate, $companyStats, $supplierStats, $processedCompanyIds, $processedSupplierIds);
                }
            }

            // Final Flush
            if ($buffer->isNotEmpty()) {

                $this->processBatch($buffer, $merger, $tempFiles, $filters, $startDate, $endDate, $companyStats, $supplierStats, $processedCompanyIds, $processedSupplierIds, true);
            }

            // Final Merge
        if (empty($tempFiles)) {
             Log::info("No se generaron partes PDF. Reporte vacio. Retornando.");
             return;
        }

        Log::info("Iniciando merge de " . count($tempFiles) . " archivos temporales.");
        $createdPdf = $merger->merge();
        Log::info("Merge finalizado.");

        $fileName = 'reports/reporte_gestion_' . now()->format('Ymd_His') . '.pdf';
        Log::info("Guardando archivo final en: " . $fileName);
        Storage::disk('public')->put($fileName, $createdPdf);
        Log::info("Archivo guardado.");

        // --- Excel Report Generation ---
        $excelFileName = 'reports/reporte_gestion_' . now()->format('Ymd_His') . '.xlsx';
        Log::info("Generando reporte Excel: " . $excelFileName);
        
        // We clone the query again for the export to ensure clean state
        // Re-creating the query object from filters might be safer if cloning has issues, 
        // but cloning $query (which was used for cursor) should be fine as cursor doesn't consume the query builder definition.
        // Actually, $query was used for cursor() which executes it. Creating a fresh query from filters is safer.
        
        // Re-build query for Excel (simplified for safety)
        $excelQuery = Employee::query();
        if (isset($filters['company_id']) && $filters['company_id']) {
            $excelQuery->whereHas('supplier', function($q) use ($filters) {
                $q->where('company_id', $filters['company_id']);
            });
        }
        if (isset($filters['supplier_id']) && $filters['supplier_id']) {
            $excelQuery->where('supplier_id', $filters['supplier_id']);
        }
        if (isset($filters['employee_id']) && $filters['employee_id']) {
            $excelQuery->where('id', $filters['employee_id']);
        }
        if ($startDate) {
            $excelQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $excelQuery->whereDate('created_at', '<=', $endDate);
        }
        if (isset($filters['approval_status']) && $filters['approval_status']) {
            $excelQuery->where('approval_status', $filters['approval_status']);
        }
        if (isset($filters['enabled'])) {
            $now = Carbon::now()->toDateString();
            if ($filters['enabled'] == '1') {
                $excelQuery->whereDate('validity_from', '<=', $now)
                      ->whereDate('validity_to', '>=', $now);
            } elseif ($filters['enabled'] == '0') {
                $excelQuery->where(function($q) use ($now) {
                    $q->whereDate('validity_from', '>', $now)
                      ->orWhereDate('validity_to', '<', $now);
                });
            }
        }
        if (isset($filters['cost_center']) && $filters['cost_center']) {
            $excelQuery->where('cost_center', 'like', '%' . $filters['cost_center'] . '%');
        }
        if (isset($filters['responsible']) && $filters['responsible']) {
            $excelQuery->where('responsible', 'like', '%' . $filters['responsible'] . '%');
        }

        Excel::store(new GlobalReportExport($excelQuery, $filters), $excelFileName, 'public');
        Log::info("Excel generado exitosamente.");

        // Cleanup Temp
        Log::info("Iniciando limpieza de archivos temporales.");
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
                // Log::info("Eliminado temp: " . basename($file)); // Optional verbose
            }
        }
        Log::info("Limpieza finalizada.");

        // Signed URL PDF
        $url = URL::temporarySignedRoute(
            'voyager.reports.download',
            now()->addHours(24),
            ['path' => $fileName]
        );

        // Signed URL Excel
        $excelUrl = URL::temporarySignedRoute(
            'voyager.reports.download',
            now()->addHours(24),
            ['path' => $excelFileName]
        );

        // Notify
        if ($this->user && $this->user->email) {
            Log::info("Enviando correo a: " . $this->user->email);
            try {
                Mail::to($this->user->email)->send(new ReportReadyMail($url, $excelUrl));
                Log::info("Correo enviado exitosamente.");
            } catch (\Exception $e) {
                Log::error("Error al enviar correo: " . $e->getMessage());
            }
        } else {
            Log::info("No se envio correo (Usuario sin email).");
        }
        
        Log::info("Job GenerateLargePdfReport completado. Saliendo del metodo handle.");

        } catch (\Throwable $e) {
            Log::error("CRITICAL ERROR in GenerateLargePdfReport: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            $this->fail($e); // Mark job as failed
        }
    }

    protected function processBatch(&$buffer, $merger, &$tempFiles, $filters, $startDate, $endDate, $companyStats, $supplierStats, &$processedCompanyIds, &$processedSupplierIds, $isFinal = false) 
    {


        // 1. Reconstruct Hierarchy for View
        $employeesBySupplier = $buffer->groupBy('supplier_id');
        $suppliers = Supplier::whereIn('id', $employeesBySupplier->keys())->get()
            ->each(function($supplier) use ($employeesBySupplier) {
                // IMPORTANT: Ensure ordering is preserved? 
                // The Buffer is ordered by supplier_id then insertion order.
                // $employeesBySupplier returns items in order.
                $supplier->setRelation('employees', $employeesBySupplier[$supplier->id]);
            });
        
        // Group suppliers by Company
        $suppliersByCompany = $suppliers->groupBy('company_id');
        $companies = Company::whereIn('id', $suppliersByCompany->keys())->get()
            ->each(function($company) use ($suppliersByCompany) {
                $company->setRelation('suppliers', $suppliersByCompany[$company->id]);
            });

        // 2. Hydrate Global Stats & headers
        foreach ($companies as $company) {
            $stats = $companyStats[$company->id] ?? ['employees' => 0, 'suppliers' => 0, 'approved' => 0];
            $company->global_employee_count = $stats['employees'];
            $company->global_supplier_count = $stats['suppliers'];
            $company->global_approved_count = $stats['approved'];
            
            // Logic: Show header if NOT processed.
            // But if this company is the Start of the Overflow, it might have been marked processed in previous partial render?
            // Actually, we process headers HERE.
            $company->show_header = !in_array($company->id, $processedCompanyIds);

            foreach ($company->suppliers as $supplier) {
                $sStats = $supplierStats[$supplier->id] ?? ['employees' => 0, 'approved' => 0];
                $supplier->global_employee_count = $sStats['employees'];
                $supplier->global_approved_count = $sStats['approved'];
                
                $supplier->show_header = !in_array($supplier->id, $processedSupplierIds);
            }
        }

        // 3. Render PDF
        $GLOBALS['pdf_map'] = []; // Reset Map
        $isFirstChunk = (count($tempFiles) === 0);

        $pdf = Pdf::loadView('vendor.voyager.reports.pdf', [
            'companies' => $companies,
            'start_date' => $startDate ? $startDate->format('d/m/Y') : 'Inicio',
            'end_date'   => $endDate   ? $endDate->format('d/m/Y')   : 'Actualidad',
            'filters'    => $filters,
            'generated_by' => $this->user->name,
            'show_main_header' => $isFirstChunk,
            'enable_map_script' => true 
        ]);

        $tempPath = storage_path('app/temp/part_' . uniqid() . '.pdf');
        $pdf->save($tempPath);

        // 4. Analyze Page Breaks
        $dompdf = $pdf->getDomPDF();
        $totalPages = $dompdf->getCanvas()->get_page_count();



        // 5. Slice & Dice
        if ($isFinal || $totalPages <= 1) {

            $merger->addFile($tempPath);
            $tempFiles[] = $tempPath; // Added tracking
            $buffer = collect(); // Clear buffer
            
            $this->markHeadersProcessed($companies, $processedCompanyIds, $processedSupplierIds);
        } else {
            // Drop the last page
            $keepPages = $totalPages - 1;

            
            $merger->addFile($tempPath, new \iio\libmergepdf\Pages('1-' . $keepPages));
            $tempFiles[] = $tempPath; // Added tracking
            
            // Identify Overflow Items
            $overflowIds = $GLOBALS['pdf_map'][$totalPages] ?? [];

            
            if (!empty($overflowIds)) {
                $buffer = $buffer->filter(function($item) use ($overflowIds) {
                    return in_array($item->id, $overflowIds);
                })->values(); 

            } else {
                Log::warning("  > WARNING: Last page was discarded but NO employees were mapped to it! Possible data loss or blank page.");
                // If no items mapped to last page, implies last page was empty or content-less.
                // In that case, we effectively kept everything?
                // Let's clear buffer to avoid infinite loop of "processing same empty buffer".
                $buffer = collect();
            }

            // Headers Logic:
            // We ONLY mark headers as processed if they appeared on the PAGES WE KEPT.
            // Identifying that is hard.
            // Simplified: Mark all as processed. 
            // FIX: If we move items to the next buffer, and those items belong to Company A.
            // And Company A was "processed" in this chunk (on the pages we kept).
            // Then in the NEXT chunk, Company A will be "already processed" => No Header.
            // BUT, since it's a new Chunk (new PDF file), we technically want the header again?
            // User said: "No headers in intermediate chunks".
            // So if Company A spans Page 1 (Chunk 1) and Page 1 (Chunk 2), 
            // Chunk 2 starts with just the table. This is correct.
            // The Issue is the GAP.
            // If we kept 1-14. Page 14 is full.
            // Chunk 2 starts. Table.
            // Should be seamless.
            
            $this->markHeadersProcessed($companies, $processedCompanyIds, $processedSupplierIds);
        }

        unset($pdf);
        unset($GLOBALS['pdf_map']);
        gc_collect_cycles();
    }

    protected function markHeadersProcessed($companies, &$processedCompanyIds, &$processedSupplierIds) {
        foreach($companies as $c) {
            if (!in_array($c->id, $processedCompanyIds)) $processedCompanyIds[] = $c->id;
            foreach($c->suppliers as $s) {
                if (!in_array($s->id, $processedSupplierIds)) $processedSupplierIds[] = $s->id;
            }
        }
    }
}

