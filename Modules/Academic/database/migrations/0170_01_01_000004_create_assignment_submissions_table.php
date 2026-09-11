<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-08 §2/BR-ACA-08-005/006/007. `attempt_number` is retained
 * per submission (BR-ACA-08-007 — resubmission is a new row, never an
 * overwrite); `final_mark` is computed once at marking time
 * (BR-ACA-08-005) and never silently recalculated afterward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->tinyInteger('attempt_number')->default(1);
            $table->text('submitted_text')->nullable();
            $table->string('submitted_link', 500)->nullable();
            $table->json('file_ids')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->integer('minutes_late')->nullable();
            $table->boolean('similarity_flag')->default(false);
            $table->json('similarity_matches')->nullable();
            $table->decimal('raw_mark', 6, 2)->nullable();
            $table->decimal('penalty_applied_percent', 5, 2)->nullable();
            $table->decimal('final_mark', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users');
            $table->timestamp('marked_at')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id', 'attempt_number'], 'assignment_submissions_attempt_unique');
            $table->index(['school_id', 'student_id', 'status'], 'assignment_submissions_student_status_idx');
            $table->index(['school_id', 'assignment_id', 'status'], 'assignment_submissions_assignment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
