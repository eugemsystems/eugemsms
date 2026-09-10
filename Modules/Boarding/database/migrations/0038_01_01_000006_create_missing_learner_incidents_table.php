<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-02 §2/§3 ⭐ — APPEND-ONLY once opened, per
 * BR-BRD-02-013/AC-BRD-02-006. Only the resolution columns
 * (`located_at`, `located_by`, `location_found`, `outcome`,
 * `outcome_note`, `guardians_notified_at`, `authorities_notified_at`,
 * `closed_by`, `closed_at`, and `current_step`/`status` as the
 * ladder advances) ever change after creation — enforced at the
 * model level (`MissingLearnerIncident::booted()`), never deletable
 * by anyone at any permission level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missing_learner_incidents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('roll_call_id')->constrained('roll_calls');
            $table->timestamp('first_missed_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_location', 150)->nullable();
            $table->string('last_seen_source', 60)->nullable();
            $table->tinyInteger('current_step')->default(1);
            $table->string('status', 20);
            $table->timestamp('located_at')->nullable();
            $table->foreignId('located_by')->nullable()->constrained('users');
            $table->string('location_found', 255)->nullable();
            $table->string('outcome', 40)->nullable();
            $table->text('outcome_note')->nullable();
            $table->timestamp('guardians_notified_at')->nullable();
            $table->timestamp('authorities_notified_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();

            $table->index(['school_id', 'status', 'first_missed_at'], 'missing_incidents_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_learner_incidents');
    }
};
