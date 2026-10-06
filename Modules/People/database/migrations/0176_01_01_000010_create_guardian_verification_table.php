<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 §3. An identity check on a guardian before they may collect a learner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_verification', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->foreignId('document_file_id')->nullable()->constrained('files');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('photo_file_id')->nullable()->constrained('files');
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['school_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_verification');
    }
};
