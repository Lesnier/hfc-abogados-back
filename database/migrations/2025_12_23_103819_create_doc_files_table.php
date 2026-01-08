<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('doc_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doc_version_id');
            $table->string('doc_type'); // e.g., 'form_931', 'policy'
            $table->string('file_path')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->foreign('doc_version_id')->references('id')->on('doc_versions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_files');
    }
};
