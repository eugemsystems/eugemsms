<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-01 §2/BR-PPL-01-021. Identity, permit and transfer documents on a learner's file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->foreignId('file_id')->constrained('files');
            $table->string('reference_number', 80)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_original_sighted')->default(false);
            $table->string('notes', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();
            $table->index(['school_id', 'student_id', 'document_type']);
            $table->index(['school_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
