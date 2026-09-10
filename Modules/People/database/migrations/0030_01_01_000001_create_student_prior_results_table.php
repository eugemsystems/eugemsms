<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C §2 "carried achievement". Specified alongside
 * `student_prior_schools` since the original People pass, but neither
 * table was built then — `ConvertApplicationToStudentAction`'s own
 * docblock already notes `previous_results` copying was skipped for
 * exactly this reason. Book H3 CMP-01's `ImportZimsecResultsAction` is
 * the first real writer. `prior_school_id` is stored unconstrained (no
 * FK): `student_prior_schools` still doesn't exist in this pass, and
 * building it is out of CMP-01's own scope — every row CMP-01 writes
 * leaves it null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_prior_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->unsignedBigInteger('prior_school_id')->nullable();
            $table->string('examination', 60);
            $table->smallInteger('exam_year');
            $table->string('candidate_number', 40)->nullable();
            $table->string('subject', 100);
            $table->string('grade', 10);
            $table->decimal('points', 5, 2)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'exam_year'], 'student_prior_results_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_prior_results');
    }
};
