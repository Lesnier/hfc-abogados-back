<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use TCG\Voyager\Facades\Voyager;

class LawFirm extends Model
{

    public function lawyers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }


    public function scopeAccess($query)
    {
        $user = auth()->user();
        if ($user->hasRole('admin') || $user->hasRole('tech_admin')) {
            return $query;
        }

        return $query->where('id', $user->lawFirm->id);
    }
}
