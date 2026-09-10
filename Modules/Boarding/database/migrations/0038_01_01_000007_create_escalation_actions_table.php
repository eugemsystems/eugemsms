<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-02 §2/§3 ⭐ — APPEND-ONLY, per BR-BRD-02-013. Every
 * notification, acknowledgement, mandatory action record, escalation,
 * and resolution against a `missing_learner_incidents` row is its own
 * new row here, never an update to a prior one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_id')->constrained('missing_learner_incidents');
            $table->tinyInteger('step_number');
            $table->string('action_type', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->text('action_taken')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['school_id', 'incident_id'], 'escalation_actions_incident_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_actions');
    }
};
