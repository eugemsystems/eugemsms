<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2/BR-ACA-07-013/014/016. Double marking keeps
 * `first_mark`/`second_mark` on separate columns so neither marker's
 * entry ever overwrites the other's — `mark_variance` is computed
 * once both are in, and a variance over threshold routes to
 * `final_marker_id`. No `ulid` — always reached through its paper and
 * candidate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paper_id')->constrained('examination_papers');
            $table->foreignId('candidate_id')->constrained('examination_candidates');
            $table->foreignId('student_id')->constrained();
            $table->decimal('raw_mark', 6, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->foreignId('first_marker_id')->nullable()->constrained('staff');
            $table->decimal('first_mark', 6, 2)->nullable();
            $table->foreignId('second_marker_id')->nullable()->constrained('staff');
            $table->decimal('second_mark', 6, 2)->nullable();
            $table->decimal('mark_variance', 6, 2)->nullable();
            $table->foreignId('final_marker_id')->nullable()->constrained('staff');
            $table->decimal('moderated_mark', 6, 2)->nullable();
            $table->foreignId('moderator_id')->nullable()->constrained('staff');
            $table->string('status', 20);
            $table->smallInteger('version')->default(1);

            $table->unique(['paper_id', 'candidate_id'], 'exam_marks_candidate_unique');
            $table->index(['school_id', 'paper_id', 'status'], 'exam_marks_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_marks');
    }
};
