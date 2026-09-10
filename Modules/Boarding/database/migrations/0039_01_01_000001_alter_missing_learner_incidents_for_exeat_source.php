<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-03 §5/BR-BRD-03-015/AC-BRD-03-006 — an overdue exeat
 * beyond the final escalation step opens a `BRD-02` missing-learner
 * incident too, and that incident has no roll call to point at.
 * `roll_call_id` becomes nullable; `escalation_profile_id` is
 * resolved once at open time (from the roll call's point, or the
 * school's default profile for an exeat-triggered incident) and
 * stored directly, so `AdvanceEscalationLadderAction` never needs to
 * re-derive it through a roll call that may not exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missing_learner_incidents', function (Blueprint $table): void {
            $table->foreignId('roll_call_id')->nullable()->change();
            $table->foreignId('escalation_profile_id')->nullable()->after('roll_call_id')->constrained('escalation_profiles');
        });
    }

    public function down(): void
    {
        Schema::table('missing_learner_incidents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('escalation_profile_id');
            $table->foreignId('roll_call_id')->nullable(false)->change();
        });
    }
};
