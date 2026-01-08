<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocFile extends Model
{
    protected $fillable = ['doc_version_id', 'doc_type', 'file_path', 'is_approved', 'note'];

    public function version()
    {
        return $this->belongsTo(DocVersion::class, 'doc_version_id');
    }
}
