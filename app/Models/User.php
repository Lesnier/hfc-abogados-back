<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;

class User extends \TCG\Voyager\Models\User
{
    use HasApiTokens, HasFactory, Notifiable;

//    /**
//     * The attributes that are mass assignable.
//     *
//     * @var array<int, string>
//     */
//    protected $fillable = [
//        'name',
//        'email',
//        'password',
//    ];
//
//    /**
//     * The attributes that should be hidden for serialization.
//     *
//     * @var array<int, string>
//     */
//    protected $hidden = [
//        'password',
//        'remember_token',
//    ];
//
//    /**
//     * The attributes that should be cast.
//     *
//     * @var array<string, string>
//     */
//    protected $casts = [
//        'email_verified_at' => 'datetime',
//        'password' => 'hashed',
//    ];


    public function lawFirm(): BelongsTo
    {
        return $this->belongsTo(LawFirm::class);
    }

    public function scopeAccess($query)
    {
        $roleCanAssign = ['lawyer' => 3, 'company' => 4, 'supplier' => 5, 'tech_admin' => 6];

        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return $query;
        }

        //Roles que usuario Administrador Tecnológico puede asignar
        $techAdminRoleCanAssign = ['lawyer', 'company', 'supplier', 'tech_admin'];

        if ($user->hasRole('tech_admin')) {
            return $query
                ->whereIn('role_id', [$roleCanAssign['lawyer'], $roleCanAssign['company'], $roleCanAssign['supplier'], $roleCanAssign['tech_admin']]);
        }

        //Roles que usuario Abogado puede asignar
        $lawyerRoleCanAssign = ['lawyer', 'company', 'supplier'];

        if ($user->hasRole('lawyer')) {
            return $query
                ->where('law_firm_id', $user->law_firm_id)
                ->whereIn('role_id', [$roleCanAssign['lawyer'], $roleCanAssign['company'], $roleCanAssign['supplier']]);
        }

        //Roles que usuario Empresa puede asignar
        $companyRoleCanAssign = ['supplier'];

        if ($user->hasRole('company')) {
            return $query
                ->where('law_firm_id', $user->law_firm_id)
                ->whereIn('role_id', [$roleCanAssign['supplier']]);
        }
        return $query->where('id', $user->role->id);
    }

    public function scopeLawyerRepresentative($query)
    {
        $user = auth()->user();

        $roleCanAssign = ['lawyer' => 3];
        if ($user->hasRole('admin') || $user->hasRole('tech_admin')) {
            return $query->where('role_id', "=", $roleCanAssign['lawyer']);
        }

        return $query->whereIn('id', []);
    }

    public function scopeCompanyRepresentative($query)
    {
        $user = auth()->user();

        $roleCanAssign = ['lawyer' => 3, 'company' => 4];

        if ($user->hasRole('admin') || $user->hasRole('tech_admin')) {
            return $query->where('role_id', "=", $roleCanAssign['company']);
        }


        if ($user->hasRole('lawyer')) {
            return $query->where('role_id', "=", $roleCanAssign['company'])->where('law_firm_id', "=", $user->law_firm_id);
        }

        return $query->whereIn('id', []);
    }

    public function scopeSupplierRepresentative($query)
    {
        $user = auth()->user();

        $roleCanAssign = ['lawyer' => 3, 'company' => 4, 'supplier' => 5];

        if ($user->hasRole('admin') || $user->hasRole('tech_admin')) {
            return $query->where('role_id', "=", $roleCanAssign['supplier']);
        }


        if ($user->hasRole('lawyer')) {
            return $query->where('role_id', "=", $roleCanAssign['supplier'])->where('law_firm_id', "=", $user->law_firm_id);
        }


        if ($user->hasRole('company')) {
            return $query->where('role_id', "=", $roleCanAssign['supplier'])->where('law_firm_id', "=", $user->law_firm_id);
        }

        return $query->whereIn('id', []);
    }
}
