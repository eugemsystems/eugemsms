<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-03 §2/§3 ⭐ — APPEND-ONLY, including every refusal
 * (BR-BRD-03-012). No UPDATE, no DELETE, ever — enforced at the model
 * level, mirroring `ScriptCustodyLogEntry`/`EscalationAction`.
 * `attempted_by_id_no` is ENCRYPTED at rest via the model's own cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('exeat_id')->nullable()->constrained('exeats');
            $table->foreignId('attempted_by_guardian_id')->nullable()->constrained('guardians');
            $table->string('attempted_by_name', 150);
            $table->text('attempted_by_id_no')->nullable();
            $table->string('claimed_relationship', 60)->nullable();
            $table->string('outcome', 20);
            $table->string('refusal_reason', 60)->nullable();
            $table->boolean('verified_by_photo')->default(false);
            $table->foreignId('gate_staff_id')->constrained('users');
            $table->foreignId('escalated_to_staff_id')->nullable()->constrained('staff');
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();

            $table->index(['school_id', 'student_id', 'occurred_at'], 'collection_attempts_student_idx');
            $table->index(['school_id', 'outcome', 'occurred_at'], 'collection_attempts_outcome_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_attempts');
    }
};
