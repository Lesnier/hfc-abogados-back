<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;

class SecurityController extends Controller
{
    public function index(){

        $employee = session('found_employee');

        return Voyager::view('vendor.voyager.security.check', [
            'employee' => $employee
        ]);
    }

    public function check(Request $request)
    {
        $request->validate(['id_number' => 'required|string|max:20']);

        $idNumber = $request->input('id_number');


        $employee = Employee::where('identification', $idNumber)->first();
         if ($employee) {
             switch ($employee->approval_status) {
                 case 'Revisión': {
                     return redirect()
                         ->route('voyager.security.index')
                         ->with([
                             'message'    => 'Pendiente de Revisión: '.$employee->name,
                             'alert-type' => 'warning',
                             'found_employee' => $employee
                         ]);
                 }
                 case 'Aprobado' : {
                     return redirect()
                         ->route('voyager.security.index')
                         ->with([
                             'message'    => 'Acceso Permitido: '.$employee->name,
                             'alert-type' => 'success',
                             'found_employee' => $employee
                         ]);
                 }
                 case 'Rechazado': {
                     return redirect()
                         ->route('voyager.security.index')
                         ->with([
                             'message'    => 'Sin Acceso: '.$employee->name,
                             'alert-type' => 'error',
                             'found_employee' => $employee
                         ]);
                 }
                 default: {
                     return redirect()
                         ->route('voyager.security.index')
                         ->with([
                             'message'    => 'Sin Estado: '.$employee->name,
                             'alert-type' => 'info',
                             'found_employee' => $employee
                         ]);
                 }
             }
         }


        return redirect()
            ->route('voyager.security.index')
            ->with([
                'message'    => 'ID no encontrado: ' . $idNumber,
                'alert-type' => 'error', // Es mejor usar 'error' o 'warning'
            ]);

    }

}

