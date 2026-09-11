<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-09 §2/§3 ⭐/BR-ACA-09-002/004. `seeded_question_order` is
 * fixed at attempt start and never reshuffled on resume;
 * `extra_time_minutes` is read once from `ACA-07`'s
 * `special_arrangements` at start, not re-resolved on every request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_candidate_attempts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_id')->constrained('cbt_tests')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->json('seeded_question_order');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_autosave_at')->nullable();
            $table->smallInteger('extra_time_minutes')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->smallInteger('tab_switch_count')->default(0);
            $table->json('focus_events')->nullable();
            $table->decimal('raw_mark', 6, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->string('status', 30);
            $table->timestamps();

            $table->unique(['test_id', 'student_id'], 'cbt_attempts_test_student_unique');
            $table->index(['school_id', 'test_id', 'status'], 'cbt_attempts_test_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_candidate_attempts');
    }
};
