<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-02 §2/§3 ⭐/BR-BRD-02-009/010. `requires_action_record`
 * is the "acknowledgement is not resolution" rule made literal —
 * `RecordEscalationActionAction` refuses to satisfy such a step with
 * empty text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('escalation_profiles');
            $table->tinyInteger('step_number');
            $table->smallInteger('delay_minutes');
            $table->foreignId('notify_role_id')->nullable()->constrained('roles');
            $table->foreignId('notify_staff_id')->nullable()->constrained('staff');
            $table->boolean('notify_guardians')->default(false);
            $table->json('channels');
            $table->boolean('requires_acknowledgement')->default(true);
            $table->boolean('requires_action_record')->default(false);
            $table->string('message_template_key', 80);

            $table->unique(['profile_id', 'step_number'], 'escalation_steps_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_steps');
    }
};
