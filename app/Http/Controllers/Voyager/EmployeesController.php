<?php

namespace App\Http\Controllers\Voyager;

use TCG\Voyager\Http\Controllers\VoyagerBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Employee;

class EmployeesController extends VoyagerBaseController
{
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
