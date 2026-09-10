<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-06 §2/BR-CORE-06-013. Queued, chunked, resumable batch
 * generation reports per-item failures without aborting the whole run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_batches', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->foreignId('template_id')->constrained('document_templates');
            $table->integer('total_count');
            $table->integer('completed_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('status', 20)->default('queued');
            $table->string('merged_file_path', 500)->nullable();
            $table->json('error_log')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_batches');
    }
};
