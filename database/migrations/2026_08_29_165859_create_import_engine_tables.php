<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('status')->default('uploaded');
            // uploaded | analyzing | needs_review | ready | processing | completed | completed_with_errors | failed | cancelled | rolled_back
            $table->json('entities_included')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('ok_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('pending_rows')->default(0);
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_staging_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->string('entity_slug');
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('resolved_data')->nullable();
            $table->string('status')->default('ok'); // ok | warning | error | needs_resolution | imported | skipped
            $table->string('action')->nullable(); // create | update
            $table->unsignedBigInteger('matched_local_id')->nullable();
            $table->json('notes')->nullable();
            $table->json('pending_fields')->nullable();
            $table->unsignedBigInteger('created_local_id')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'entity_slug', 'status']);
        });

        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->string('entity_slug');
            $table->string('external_id')->nullable();
            $table->string('identification')->nullable();
            $table->unsignedBigInteger('local_id');
            $table->string('action')->default('create'); // create | update
            $table->timestamps();

            $table->unique(['entity_slug', 'external_id']);
            $table->index(['entity_slug', 'identification']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mappings');
        Schema::dropIfExists('import_staging_rows');
        Schema::dropIfExists('import_batches');
    }
};
