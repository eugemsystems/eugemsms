<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-08 §2/BR-BRD-08-015/016 — separate from casework.
 * `session_notes` is `SecondaryEncrypted`, visible to the recording
 * counsellor and the safeguarding lead only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselling_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('counsellor_staff_id')->constrained('staff');
            $table->timestamp('session_at');
            $table->smallInteger('duration_minutes')->nullable();
            $table->string('session_type', 30);
            $table->string('referral_source', 30)->nullable();
            $table->string('presenting_theme', 120)->nullable();
            $table->text('session_notes')->nullable();
            $table->boolean('risk_indicators_present')->default(false);
            $table->foreignId('escalated_to_case_id')->nullable()->constrained('safeguarding_cases');
            $table->date('next_session_on')->nullable();
            $table->boolean('attended')->default(true);

            $table->index(['school_id', 'student_id', 'session_at']);
            $table->index(['school_id', 'counsellor_staff_id', 'session_at'], 'counselling_sessions_counsellor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselling_sessions');
    }
};
