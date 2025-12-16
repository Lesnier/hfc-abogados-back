<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Company extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

protected $table = 'companies';
    protected $fillable = ["identification", "name", "country", "user_id", "logo", "phone", "email", "law_firm_id"];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function law_firm()
    {
        return $this->belongsTo(LawFirm::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }
    public function scopeBrowserList($query)
    {
        $user = auth()->user();
        if ($user->hasRole('admin') || $user->hasRole('tech_admin')) {
            return $query;
        }


        //Roles que usuario Abogado puede asignar

        if ($user->hasRole('lawyer')) {
            return $query
                ->join('law_firms', 'companies.law_firm_id', '=', 'law_firms.id')
                ->where('law_firms.id', '=', $user->law_firm_id)
                ->select('companies.*');

        }

        //Mostrar solo empresa a la que pertenece el usuario Empresario

        if ($user->hasRole('company')) {
            return $query
                ->join('users', 'users.id', '=', 'companies.user_id')
                ->where('companies.user_id', '=', $user->id)
                ->select('companies.*');

        }


        return $query->whereIn('id', []);
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
                ->join('law_firms', 'companies.law_firm_id', '=', 'law_firms.id')
                ->where('law_firms.id', '=', $user->law_firm_id)
                ->select('companies.*');

        }

        //Mostrar solo empresa a la que pertenece el usuario Empresario

        if ($user->hasRole('company')) {
            return $query
                ->join('users', 'users.id', '=', 'companies.user_id')
                ->where('companies.user_id', '=', $user->id)
                ->select('companies.*');

        }


        return $query->whereIn('id', []);
    }
}
