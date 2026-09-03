<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'file_name', 'status', 'entities_included',
        'total_rows', 'ok_rows', 'warning_rows', 'error_rows', 'pending_rows',
        'imported_by', 'executed_at', 'rolled_back_at',
    ];

    protected $casts = [
        'entities_included' => 'array',
        'executed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function stagingRows(): HasMany
    {
        return $this->hasMany(ImportStagingRow::class);
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
