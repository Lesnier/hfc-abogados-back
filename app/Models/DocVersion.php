<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocVersion extends Model
{
    protected $fillable = ['employee_id', 'version_number', 'effective_date'];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function files()
    {
        return $this->hasMany(DocFile::class);
    }
}
