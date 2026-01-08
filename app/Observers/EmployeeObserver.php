<?php

namespace App\Observers;

use App\Models\Employee;

use App\Models\DocFile;
use App\Models\DocVersion;
use Illuminate\Support\Facades\Log;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        // Mapeo de columnas de documentos
        $docColumns = [
            'form_931', 'policy', 'life_insurance', 'salary_receipt', 
            'repetition', 'indemnity', 'proof_discharge', 'arca_termination_form'
        ];

        foreach ($docColumns as $col) {
            if ($employee->isDirty($col)) {
                $newValue = $employee->$col;
                
                // Find latest version
                $latestVersion = $employee->docVersions()->orderBy('version_number', 'desc')->first();

                if ($latestVersion) {
                    // Normalize value: If not JSON, try to wrap it if it looks like a path?
                    // Voyager usually sends "[]" for empty or a JSON array string.
                    // Or sometimes empty string/null.
                    
                    // Logic: Just save whatever Voyager saved. UI handles decoding.
                    // But if it's strictly empty or "[]", we might want to ensure consistent storage?
                    // DocVersioningController stores `json_encode([['download_link' => ...]])`.
                    // Voyager might store differently.

                    // If user DELETES in Voyager, it becomes null or "[]".
                    
                    DocFile::updateOrCreate(
                        [
                            'doc_version_id' => $latestVersion->id,
                            'doc_type' => $col
                        ],
                        [
                            'file_path' => $newValue,
                            'is_approved' => false // Reset approval on change? Maybe. User removed/changed file.
                        ]
                    );
                }
            }
        }

        // Check for Notification Checkbox from Request (Voyager Form)
        // Log request data for debugging
        Log::info("EmployeeObserver: updated event fired for Employee ID: " . $employee->id);
        Log::info("Request inputs: ", request()->all());

        if (request()->has('notify_supplier') && request()->input('notify_supplier') == '1') {
            Log::info("EmployeeObserver: 'notify_supplier' checkbox IS CHECKED for employee: " . $employee->id);
            
            $supplier = $employee->supplier;
            
            if (!$supplier) {
                Log::warning("EmployeeObserver: Supplier relation returned null for employee: " . $employee->id);
            } else {
                Log::info("EmployeeObserver: Supplier found. ID: " . $supplier->id);
                
                // Assuming Supplier -> User relationship via user_id
                $user = \App\Models\User::find($supplier->user_id);
                
                if ($user && $user->email) {
                    Log::info("EmployeeObserver: Supplier User found. Email: " . $user->email);

                    $latestVersion = $employee->docVersions()->orderBy('version_number', 'desc')->first();
                    
                    if ($latestVersion) {
                        Log::info("EmployeeObserver: Latest version found: " . $latestVersion->version_number);
                        $files = $latestVersion->files;
                        try {
                            \Illuminate\Support\Facades\Mail::to($user->email)->send(
                                new \App\Mail\DocumentStatusMail($employee, $latestVersion, $files)
                            );
                            Log::info("EmployeeObserver: Email sent successfully to: " . $user->email);
                        } catch (\Exception $e) {
                            Log::error("EmployeeObserver: Error sending document status email: " . $e->getMessage());
                        }
                    } else {
                        Log::warning("EmployeeObserver: No document version found for employee: " . $employee->id);
                    }
                } else {
                    Log::warning("EmployeeObserver: Supplier user or email not found. Supplier User ID: " . ($supplier->user_id ?? 'null'));
                }
            }
        } else {
            Log::info("EmployeeObserver: 'notify_supplier' checkbox NOT checked or value is not 1.");
        }
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        //
    }
}
