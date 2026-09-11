<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-08 §2/BR-ACA-08-008 ⭐. `assessment_type_id` is the
 * spec's own column (the ACA-05 category, e.g. "Coursework") — it is
 * NOT the concrete assessable event. `assessment_id` is this
 * migration's own addition (undocumented in the spec's literal table):
 * the specific `assessments` row this assignment's marks sync into,
 * created once via `CreateAssessmentAction` at assignment-creation
 * time (deviation noted per this project's convention of documenting
 * spec extensions inline). Without it, `MarkAssignmentSubmissionAction`
 * would have to re-derive "which assessment" from
 * (subject, teaching_group, term, type) every time, which breaks the
 * moment two assignments of the same type exist for the same group in
 * the same term — each assignment is its own assessable event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_space_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('instructions');
            $table->json('attachment_file_ids')->nullable();
            $table->decimal('max_mark', 6, 2)->nullable();
            $table->foreignId('rubric_id')->nullable()->constrained('project_rubrics');
            $table->foreignId('assessment_type_id')->nullable()->constrained('assessment_types');
            $table->foreignId('assessment_id')->nullable()->constrained('assessments');
            $table->timestamp('opens_at');
            $table->timestamp('due_at');
            $table->string('late_policy', 20);
            $table->decimal('late_penalty_percent_per_day', 5, 2)->nullable();
            $table->boolean('allows_resubmission')->default(false);
            $table->string('submission_type', 20);
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'course_space_id', 'due_at'], 'assignments_space_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
