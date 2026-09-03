<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportMapping extends Model
{
    protected $fillable = [
        'import_batch_id', 'entity_slug', 'external_id', 'identification', 'local_id', 'action',
    ];
}
