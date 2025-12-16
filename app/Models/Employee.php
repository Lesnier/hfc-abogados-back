<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Employee extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'employees';
    protected $fillable = ["identification", "name", "cuil", "condition", "suitable_income", "responsible", "approval_status", "cost_center", "validity_from", "validity_to", "supplier_id"];


    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    

    public function scopeAccess($query)
    {
        $user = auth()->user();
        if ($user->hasRole('admin') || $user->hasRole('tech_admin')) {
            return $query;
        }


        //Roles que usuario Abogado puede asignar

        if ($user->hasRole('lawyer')) {
            return $query
                ->join('suppliers', 'suppliers.id', '=', 'employees.supplier_id')
                ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                ->join('law_firms', 'companies.law_firm_id', '=', 'law_firms.id')
                ->where('law_firms.id', '=', $user->law_firm_id)
                ->select('employees.*');

        }

        //Roles que usuario Empresa puede asignar

        if ($user->hasRole('company')) {
            return $query
                ->join('suppliers', 'suppliers.id', '=', 'employees.supplier_id')
                ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                ->join('law_firms', 'companies.law_firm_id', '=', 'law_firms.id')
                ->where('companies.user_id', '=', $user->id)
                ->where('law_firms.id', '=', $user->law_firm_id)
                ->select('employees.*');
        }

        //Roles que usuario Proveedor puede asignar

        if ($user->hasRole('supplier')) {
            return $query
                ->join('suppliers', 'suppliers.id', '=', 'employees.supplier_id')
                ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                ->join('law_firms', 'companies.law_firm_id', '=', 'law_firms.id')
                ->where('suppliers.user_id', '=', $user->id)
                ->where('law_firms.id', '=', $user->law_firm_id)
                ->select('employees.*');

        }

        return $query->whereIn('id', []);

    }
}
