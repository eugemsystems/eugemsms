<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-07 §2. The spec names this column `fee_line_id`
 * against a `LearnerFeeLine`, but the real `FIN-02` engine
 * `JoinActivityAction` reuses for "charge a fee component to a
 * student's account" — `Modules\Finance\Domain\Actions\
 * CreateAdHocChargeAction` — returns an `AdHocCharge` (pending
 * approval, invoiced separately later), never a `LearnerFeeLine`
 * directly. This column is renamed `ad_hoc_charge_id` and points at
 * `ad_hoc_charges` to match what that action actually produces,
 * rather than forcing a `learner_fee_lines` FK nothing here would
 * ever populate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('activity_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->string('role', 30)->nullable();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->boolean('consent_received')->default(false);
            $table->boolean('medical_cleared')->nullable();
            $table->string('billing_status', 20)->default('pending');
            $table->foreignId('ad_hoc_charge_id')->nullable()->constrained('ad_hoc_charges');
            $table->string('status', 20);

            $table->unique(['school_id', 'term_id', 'activity_id', 'student_id'], 'activity_memberships_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_memberships');
    }
};
