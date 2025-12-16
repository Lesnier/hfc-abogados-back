<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Supplier extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'suppliers';
    protected $fillable = ["identification", "name", "complaint_cc", "risk_end", "cbu_checking_account", "name_bank", "number_checking_account", "company_id","approval_status", "user_id"];
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
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
                ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                ->join('law_firms', 'companies.law_firm_id', '=', 'law_firms.id')
                ->where('law_firms.id', '=', $user->law_firm_id)
                ->select('suppliers.*');
        }

        //Roles que usuario Empresa puede asignar

        if ($user->hasRole('company')) {
            return $query
                ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                ->join('law_firms', 'companies.law_firm_id', '=', 'law_firms.id')
                ->where('companies.user_id', '=', $user->id)
                ->where('law_firms.id', '=', $user->law_firm_id)
                ->select('suppliers.*');
        }

        //Roles que usuario Proveedor puede asignar

        if ($user->hasRole('supplier')) {
            return $query
                ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                ->join('law_firms', 'companies.law_firm_id', '=', 'law_firms.id')
                ->where('suppliers.user_id', '=', $user->id)
                ->where('law_firms.id', '=', $user->law_firm_id)
                ->select('suppliers.*');
        }

        return $query->whereIn('id', []);

    }
}
