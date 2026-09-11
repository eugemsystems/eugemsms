<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-11 §2/BR-ACA-11-005/006. `teacher_acknowledged`/
 * `teacher_comments` are the ONLY fields the observed teacher may
 * ever write — enforced at the Action layer
 * (`AddObservationTeacherCommentAction`), not just by convention here.
 * `follow_up_observation_id` self-references this same table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_observations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('observed_staff_id')->constrained('staff');
            $table->foreignId('observer_staff_id')->constrained('staff');
            $table->foreignId('rubric_id')->constrained('observation_rubrics');
            $table->timestamp('observed_at');
            $table->string('class_observed', 80)->nullable();
            $table->foreignId('subject_id')->nullable()->constrained();
            $table->json('scores');
            $table->text('strengths_noted')->nullable();
            $table->text('areas_for_development')->nullable();
            $table->string('overall_rating', 30)->nullable();
            $table->boolean('teacher_acknowledged')->default(false);
            $table->text('teacher_comments')->nullable();
            $table->foreignId('follow_up_observation_id')->nullable()->constrained('lesson_observations');
            $table->timestamps();

            $table->index(['school_id', 'observed_staff_id', 'observed_at'], 'lesson_observations_staff_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_observations');
    }
};
