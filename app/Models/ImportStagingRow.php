<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportStagingRow extends Model
{
    protected $fillable = [
        'import_batch_id', 'entity_slug', 'row_number', 'raw_data', 'resolved_data',
        'status', 'action', 'matched_local_id', 'notes', 'pending_fields', 'created_local_id',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'resolved_data' => 'array',
        'notes' => 'array',
        'pending_fields' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
