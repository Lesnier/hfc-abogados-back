<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unique('identification');
        });

        // Update Voyager BREAD validation rule for identification
        $dataTypeId = DB::table('data_types')->where('slug', 'companies')->value('id');
        if ($dataTypeId) {
            $dataRow = DB::table('data_rows')
                ->where('data_type_id', $dataTypeId)
                ->where('field', 'identification')
                ->first();
            if ($dataRow) {
                $details = json_decode($dataRow->details, true) ?: [];
                $details['validation'] = [
                    'rule' => 'required|unique:companies,identification'
                ];
                DB::table('data_rows')
                    ->where('id', $dataRow->id)
                    ->update(['details' => json_encode($details)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert Voyager BREAD validation rule
        $dataTypeId = DB::table('data_types')->where('slug', 'companies')->value('id');
        if ($dataTypeId) {
            $dataRow = DB::table('data_rows')
                ->where('data_type_id', $dataTypeId)
                ->where('field', 'identification')
                ->first();
            if ($dataRow) {
                $details = json_decode($dataRow->details, true) ?: [];
                unset($details['validation']);
                DB::table('data_rows')
                    ->where('id', $dataRow->id)
                    ->update(['details' => json_encode($details)]);
            }
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['identification']);
        });
    }
};
