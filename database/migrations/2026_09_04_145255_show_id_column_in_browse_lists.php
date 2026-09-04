<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Slugs de los BREAD a los que se les habilita la columna ID en el listado (browse).
     */
    private array $slugs = ['users', 'law-firms', 'companies', 'suppliers', 'employees'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->slugs as $slug) {
            $dataTypeId = DB::table('data_types')->where('slug', $slug)->value('id');
            if (!$dataTypeId) {
                continue;
            }

            DB::table('data_rows')
                ->where('data_type_id', $dataTypeId)
                ->where('field', 'id')
                ->update(['browse' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->slugs as $slug) {
            $dataTypeId = DB::table('data_types')->where('slug', $slug)->value('id');
            if (!$dataTypeId) {
                continue;
            }

            DB::table('data_rows')
                ->where('data_type_id', $dataTypeId)
                ->where('field', 'id')
                ->update(['browse' => 0]);
        }
    }
};
