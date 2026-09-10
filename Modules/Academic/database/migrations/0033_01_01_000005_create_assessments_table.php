<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2/BR-ACA-05-004. Weight totals are validated at
 * computation time, not save time — a subject's assessments for a
 * term can be built up incrementally without every intermediate state
 * summing to 100%.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('assessment_type_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('grade_level_id')->nullable()->constrained();
            $table->foreignId('class_id')->nullable()->constrained('school_classes');
            $table->foreignId('teaching_group_id')->nullable()->constrained();
            $table->string('title', 150);
            $table->decimal('max_mark', 6, 2);
            $table->decimal('weight_percent', 5, 2);
            $table->date('assessed_on')->nullable();
            $table->foreignId('grading_scale_id')->nullable()->constrained('grading_scales')->nullOnDelete();
            $table->string('status', 20);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'term_id', 'subject_id', 'status'], 'assessments_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
