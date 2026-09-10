<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('definition_key', 60);
            $table->foreignId('source_file_id')->constrained('files');
            $table->json('column_mapping');
            $table->json('options')->nullable();
            $table->string('status', 20)->default('mapping');
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->json('validation_report')->nullable();
            $table->foreignId('error_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('imported_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();

            $table->index(['school_id', 'definition_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
