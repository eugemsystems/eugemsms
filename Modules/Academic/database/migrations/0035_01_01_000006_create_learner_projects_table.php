<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §2/§4 ⭐ — one per learner per brief. `outcome` is
 * exactly what `ContinuousAssessmentProvider::outcomeFor()` reads for
 * a `sbp`-instrument year; never deleted, only ever moved to
 * `exempt` on a subject drop (BR-ACA-06-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_projects', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('brief_id')->constrained('project_briefs');
            $table->foreignId('student_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->string('project_title', 255)->nullable();
            $table->string('status', 20);
            $table->decimal('raw_mark', 6, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->string('outcome', 30)->nullable();
            $table->json('criterion_marks')->nullable();
            $table->foreignId('marker_staff_id')->nullable()->constrained('staff');
            $table->timestamp('marked_at')->nullable();
            $table->text('marker_comment')->nullable();
            $table->foreignId('moderator_staff_id')->nullable()->constrained('staff');
            $table->timestamp('moderated_at')->nullable();
            $table->decimal('moderated_mark', 6, 2)->nullable();
            $table->text('moderation_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->string('exemption_reason', 255)->nullable();
            $table->smallInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'brief_id', 'student_id'], 'learner_projects_unique');
            $table->index(['school_id', 'academic_year_id', 'student_id', 'subject_id'], 'learner_projects_student_idx');
            $table->index(['school_id', 'brief_id', 'status'], 'learner_projects_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_projects');
    }
};
