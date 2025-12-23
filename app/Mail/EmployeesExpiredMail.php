<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class EmployeesExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employees;
    public $lawFirmName;
    public $category;
    public $title;
    public $bodyContent;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($employees, $lawFirmName, $category, $title, $bodyContent)
    {
        $this->employees = $employees;
        $this->lawFirmName = $lawFirmName;
        $this->category = $category;
        $this->title = $title;
        $this->bodyContent = $bodyContent;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Reconstruct the companies hierarchy for the PDF view
        // The view iterates: $company->suppliers -> supplier->employees
        
        $companies = $this->employees->groupBy(function($employee) {
             return $employee->supplier->company->id;
        })->map(function($employeesInCompany) {
             // Get the company object from the first employee
             // (Assuming eager loading or access is possible)
             $first = $employeesInCompany->first();
             $company = $first->supplier->company;
             
             // Group by Supplier within this company
             $suppliers = $employeesInCompany->groupBy('supplier_id')->map(function($employeesInSupplier) {
                 $supplier = $employeesInSupplier->first()->supplier;
                 // Manually set the relation 'employees' for the view to iterate
                 $supplier->setRelation('employees', $employeesInSupplier);
                 return $supplier;
             });
             
             $company->setRelation('suppliers', $suppliers);
             return $company;
        });

        $pdf = Pdf::loadView('vendor.voyager.reports.pdf', [
            'companies' => $companies,
            'start_date' => null, 
            'end_date' => Carbon::now(),
            'filters' => [
                'approval_status' => 'Rechazado (Vencido)', 
                'enabled' => 'No'
            ],
            'generated_by' => 'Sistema Legal Auditex'
        ]);

        return $this->view('emails.generic')
                    ->subject($this->title)
                    ->with([
                        'category' => $this->category,
                        'title' => $this->title,
                        'body' => $this->bodyContent,
                        'lawFirmName' => $this->lawFirmName
                    ])
                    ->attachData($pdf->output(), 'reporte_vencimientos.pdf', [
                        'mime' => 'application/pdf',
                    ]);
    }
}
