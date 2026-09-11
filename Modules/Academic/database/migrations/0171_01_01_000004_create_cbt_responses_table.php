<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-09 §2/§3 ⭐/BR-ACA-09-001. One row per question per
 * attempt, autosaved — `last_saved_at` is server-set on every write,
 * never trusted from a client timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('cbt_candidate_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('question_bank');
            $table->json('response_value')->nullable();
            $table->boolean('is_flagged_by_candidate')->default(false);
            $table->boolean('auto_mark_correct')->nullable();
            $table->decimal('mark_awarded', 6, 2)->nullable();
            $table->text('manual_feedback')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users');
            $table->timestamp('last_saved_at', 3);
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id'], 'cbt_responses_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_responses');
    }
};
