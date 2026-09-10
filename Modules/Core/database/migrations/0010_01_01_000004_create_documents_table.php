<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-06 §2. Every generated artefact — `template_id` +
 * `template_version` pin the exact version used (BR-CORE-06-007/008),
 * `file_hash` is the determinism guarantee (BR-CORE-06-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 40);
            $table->foreignId('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->smallInteger('template_version')->nullable();
            $table->string('number', 80)->nullable();
            $table->string('documentable_type')->nullable();
            $table->unsignedBigInteger('documentable_id')->nullable();
            $table->string('file_path', 500);
            $table->char('file_hash', 64);
            $table->bigInteger('file_size')->nullable();
            $table->string('verification_code', 40)->nullable()->unique();
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->integer('download_count')->default(0);

            $table->index(['school_id', 'document_type', 'generated_at']);
            $table->index(['documentable_type', 'documentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
