<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable: an appraisal created before a school configures its first rubric (or a
 * school that never does) keeps working exactly as before, scored as free-form JSON —
 * see `SubmitSelfAssessmentAction`/`SubmitAppraiserAssessmentAction`'s own fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_appraisals', function (Blueprint $table): void {
            $table->foreignId('rubric_id')->nullable()->after('appraiser_staff_id')->constrained('staff_appraisal_rubrics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_appraisals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rubric_id');
        });
    }
};
