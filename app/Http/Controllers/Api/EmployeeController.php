<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Supplier;
use Illuminate\Http\Request;


class EmployeeController extends Controller
{
    public function verify(Request $request): \Illuminate\Http\JsonResponse
    {
        $dni = $request->input('dni');

        $employee = Employee::where('identification', $dni)->first();

        if ($employee) {
            $supplier = Supplier::find($employee->supplier_id);
            $companyName = $supplier ? $supplier->name : 'Unknown Company';

            $message = "";
            switch ($employee->approval_status){
                case "Revisión": $message = "Empleado Pendiente de Revisión"; break;
                case "Aprobado": $message = "Empleado con Acceso Permitido"; break;
                default: $message = "Empleado Sin Acceso"; break;
            }

            $responseData = [
                'success' => true,
                'employee' => [
                    'id' => $employee->id,
                    'dni' => $employee->identification,
                    'name' => $employee->name,
                    'lastName' => 'null', // Hardcoded
                    'position' => 'null', // Hardcoded
                    'company' => $companyName,
                    'photoUrl' => 'null', // Hardcoded
                    'status' => $employee->approval_status,
                    'isAuthorized' => $employee->approval_status === 'Aprobado', // Hardcoded
                    'authorizationDetails' => [
                        'startDate' => 'null', // Hardcoded
                        'endDate' => 'null', // Hardcoded
                        'shifts' => ['Mañana', 'Tarde'], // Hardcoded
                        'zone' => 'null', // Hardcoded
                        'notes' => 'null', // Hardcoded
                    ],
                ],
                'message' => $message,
            ];

            return response()->json($responseData);
        } else {
            return response()->json(['success' => false, 'message' => 'Empleado no encontrado'], 404);
        }
    }
}
