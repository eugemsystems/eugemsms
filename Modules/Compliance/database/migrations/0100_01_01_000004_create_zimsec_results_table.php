<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-01 §2/BR-CMP-01-010/011. `ImportZimsecResultsAction`
 * maps `candidate_number` back to `zimsec_candidates`/`student_id` —
 * an unmatched candidate number is reported, never silently dropped.
 * Every imported row also writes a `student_prior_results` row (Book
 * C, `Modules\People`) so results are visible on transcripts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zimsec_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('zimsec_registrations')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('candidate_number', 40);
            $table->string('subject_code', 20);
            $table->string('subject_name', 150);
            $table->string('grade', 10);
            $table->decimal('points', 5, 2)->nullable();
            $table->boolean('is_provisional')->default(false);
            $table->timestamp('imported_at');
            $table->foreignId('imported_by')->constrained('users');
            $table->unsignedBigInteger('source_file_id')->nullable();

            $table->unique(['registration_id', 'student_id', 'subject_code'], 'zimsec_results_candidate_subject_unique');
            $table->index(['school_id', 'student_id'], 'zimsec_results_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zimsec_results');
    }
};
