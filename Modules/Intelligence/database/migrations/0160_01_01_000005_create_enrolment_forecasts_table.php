<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-03 §2/BR-INT-03-007. `confidence_band` is driven by how
 * many terms of `Modules\People\Models\StudentEnrolment` history exist
 * for the grade level, never hidden when thin (BR-INT-03-007/AC-INT-03-006).
 *
 * The spec's own sketch lists no UNIQUE constraint for this table, but
 * every other "CACHE, rebuilt nightly" table in this book set has one
 * (`learner_risk_scores`, `fee_default_risk_scores`, `warehouse_snapshots`)
 * — without it, a nightly recompute would accumulate an unbounded
 * duplicate row per target year/grade every run. Added here for the
 * same reason, consistent with the established codebase convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolment_forecasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();
            $table->smallInteger('projected_intake')->nullable();
            $table->smallInteger('projected_attrition')->nullable();
            $table->string('confidence_band', 20);
            $table->string('basis_note', 500);
            $table->timestamp('computed_at');

            $table->unique(['school_id', 'academic_year_id', 'grade_level_id'], 'enrolment_forecasts_school_year_grade_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolment_forecasts');
    }
};
