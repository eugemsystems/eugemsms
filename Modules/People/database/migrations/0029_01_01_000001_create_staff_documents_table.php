<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2/BR-PPL-04-018 — deferred from the module's first
 * pass (staff/contracts/establishment/departments), built now
 * alongside the rest of B13.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->foreignId('file_id')->constrained('files');
            $table->string('reference_number', 80)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->boolean('is_verified')->default(false);

            $table->index(['school_id', 'expires_on']);
            $table->index(['school_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_documents');
    }
};
