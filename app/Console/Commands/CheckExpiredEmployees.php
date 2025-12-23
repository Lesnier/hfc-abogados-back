<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\LawFirm;
use App\Models\User;
use App\Mail\EmployeesExpiredMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckExpiredEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:check-expired {--send-emails=true : Whether to send emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired employees, update status to Rechazado, and notify suppliers/law firms.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting expired employees check...');

        // 1. Identify Expired Employees
        // "dia actual sea igual o superior a la fecha de validity_to"
        // validity_to <= today (assuming validity_to is the last valid day, or expiry day? 
        // Usually validity_to is the END of validity. 
        // If today is 24th, and validity_to is 23rd, it is expired.
        // User said: "dia actual sea igual o superior a la fecha de validity_to".
        // If today == validity_to, then EXPIRED? That implies validity_to is the Expiry Date (exclusive?).
        // Let's stick to user request: now >= validity_to.
        
        $today = Carbon::today(); 
        
        // Find employees that are NOT already rejected/baja? 
        // Updating 'approval_status' to 'Rechazado'.
        // Assuming 'Baja' is also a terminal state, we might not want to touch 'Baja'.
        // So we filter where status NOT IN ['Rechazado', 'Baja']?
        // Or just != 'Rechazado'.
        
        $expiredEmployees = Employee::whereDate('validity_to', '<=', $today)
                                    ->where('approval_status', '!=', 'Rechazado')
                                    ->where('approval_status', '!=', 'Baja') // Assuming we don't overwrite Baja
                                    ->with(['supplier.company.law_firm', 'supplier.company']) // Preload for emails
                                    ->get();

        $count = $expiredEmployees->count();
        $this->info("Found {$count} expired employees.");

        if ($count === 0) {
            return;
        }

        // 2. Group for emails mostly BEFORE update (to have data) but we want to report them as 'Rechazado'.
        // The object instances in memory will keep their old status unless we refresh them, 
        // BUT for the Report we might want to show them as 'Rechazado' (Vencido).
        // The PDF view shows the current status. 
        // Let's update them in DB first.
        
        Employee::whereIn('id', $expiredEmployees->pluck('id'))->update(['approval_status' => 'Rechazado']);
        
        // Update instances in memory for the PDF report
        foreach($expiredEmployees as $emp) {
            $emp->approval_status = 'Rechazado';
        }

        $sendEmails = $this->option('send-emails') !== 'false'; // string 'true' or 'false' if passed from cli interface sometimes

        if (!$sendEmails) {
            $this->info('Email sending disabled.');
            return;
        }

        // 3. Send Emails to Suppliers
        $bySupplier = $expiredEmployees->groupBy('supplier_id');

        foreach ($bySupplier as $supplierId => $employees) {
            $supplier = $employees->first()->supplier;
            
            // Suppliers might have a User linked via user_id?
            // User model says Supplier has 'user_id' in fillable.
            // But Supplier model didn't explicitly show 'user' relation in the view I saw.
            // Let's assume we can get it via user_id.
            $supplierUser = User::find($supplier->user_id);

            if ($supplierUser && $supplierUser->email) {
                $lawFirmName = $employees->first()->supplier->company->law_firm->name ?? 'Firma Legal';
                
                try {
                    Mail::to($supplierUser->email)->send(new EmployeesExpiredMail(
                        $employees,
                        $lawFirmName,
                        'Aviso de Vencimiento',
                        'Reporte de Empleados Vencidos',
                        '<p>Estimado Proveedor,</p><p>Adjunto encontrará el reporte de sus empleados cuyo periodo de validez ha finalizado al día de hoy. Su estado ha sido actualizado a <strong>Rechazado</strong>.</p>'
                    ));
                    $this->info("Email sent to Supplier: {$supplier->name}");
                } catch (\Exception $e) {
                    $this->error("Failed to email Supplier {$supplier->name}: " . $e->getMessage());
                }
            } else {
                $this->warn("Supplier {$supplier->name} has no linked user/email.");
            }
        }

        // 4. Send Emails to Law Firms (Owners)
        // Group all expired employees by LawFirm
        $byLawFirm = $expiredEmployees->groupBy(function($emp) {
            return $emp->supplier->company->law_firm_id;
        });

        foreach ($byLawFirm as $lawFirmId => $employees) {
            // Get Law Firm
            $lawFirm = LawFirm::find($lawFirmId);
            if (!$lawFirm) continue;

            // Get owner/lawyers of this firm
            // Assuming all users linked to this lawfirm should get it? Or search for a 'lawyer' role?
            // "Dueño de la Firma".
            // Let's get all users with role 'lawyer' (or admin?) associated with this law_firm_id.
            $lawyers = User::where('law_firm_id', $lawFirmId)
                           ->whereHas('role', function($q) {
                               $q->where('name', 'lawyer'); // Assuming 'lawyer' is the role key
                           })->get();
            
            // If no lawyers found, maybe try company owner? No, Law Firm owner.
            if ($lawyers->isEmpty()) {
                // Fallback: try any user associated
                 $lawyers = User::where('law_firm_id', $lawFirmId)->get();
            }

            foreach ($lawyers as $lawyer) {
                if ($lawyer->email) {
                   try {
                        Mail::to($lawyer->email)->send(new EmployeesExpiredMail(
                            $employees,
                            $lawFirm->name,
                            'Reporte Diario',
                            'Resumen de Empleados Vencidos (Toda la Firma)',
                            '<p>Estimado/a,</p><p>Adjunto encontrará el reporte consolidado de todos los empleados pertenecientes a los proveedores de su firma que han vencido al día de hoy. Sus estados han sido actualizados a <strong>Rechazado</strong>.</p>'
                        ));
                         $this->info("Email sent to Law Firm User: {$lawyer->name}");
                   } catch (\Exception $e) {
                         $this->error("Failed to email Law Firm User {$lawyer->name}: " . $e->getMessage());
                   }
                }
            }
        }
        
        $this->info('Expired check completed.');
    }
}
