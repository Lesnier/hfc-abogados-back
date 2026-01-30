<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;

class Role extends \TCG\Voyager\Models\Role
{

    public function scopeAccess($query)
    {
        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return $query;
        }

        //Roles que usuario Administrador Tecnológico puede asignar
        $techAdminRoleCanAssign = ['lawyer','company','supplier','security'];

        if ($user->hasRole('tech_admin')) {
            return $query->whereIn('name', $techAdminRoleCanAssign);
        }

        //Roles que usuario Abogado puede asignar
        $lawyerRoleCanAssign = ['lawyer','company','supplier','security'];

        if ($user->hasRole('lawyer')) {
            return $query->whereIn('name', $lawyerRoleCanAssign);
        }

        //Roles que usuario Empresa puede asignar
        $companyRoleCanAssign = ['supplier'];

        if ($user->hasRole('company')) {
            return $query->whereIn('name', $companyRoleCanAssign);
        }
        return $query->where('id', $user->role->id);
    }
}
