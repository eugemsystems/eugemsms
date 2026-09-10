<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-01 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabi', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('framework_id')->constrained('curriculum_frameworks');
            $table->string('title', 200);
            $table->string('version', 30)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->json('topics')->nullable();
            $table->boolean('is_active')->default(true);

            $table->index(['school_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};
