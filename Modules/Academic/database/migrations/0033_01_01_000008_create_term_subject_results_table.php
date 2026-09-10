<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2/§3 ⭐ — aggregated per learner per subject per
 * term, rebuilt by `ComputeTermSubjectResultsAction`, never itself
 * authoritative.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_subject_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->decimal('coursework_percent', 5, 2)->nullable();
            $table->decimal('examination_percent', 5, 2)->nullable();
            $table->decimal('continuous_percent', 5, 2)->nullable();
            $table->decimal('final_percent', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->decimal('points', 5, 2)->nullable();
            $table->smallInteger('class_position')->nullable();
            $table->smallInteger('class_size')->nullable();
            $table->smallInteger('level_position')->nullable();
            $table->decimal('subject_average', 5, 2)->nullable();
            $table->string('teacher_comment', 500)->nullable();
            $table->foreignId('teacher_staff_id')->nullable()->constrained('staff');
            $table->string('sbp_outcome', 30)->nullable();
            $table->string('sbp_grade', 10)->nullable();
            $table->boolean('is_finalised')->default(false);
            $table->timestamp('computed_at')->nullable();

            $table->unique(['school_id', 'term_id', 'student_id', 'subject_id'], 'term_subject_results_unique');
            $table->index(['school_id', 'term_id', 'subject_id', 'final_percent'], 'term_subject_results_rank_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_subject_results');
    }
};
