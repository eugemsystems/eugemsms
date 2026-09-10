<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-08 §2/BR-BRD-08-019 — mandatory review date. `risk_factors`,
 * `protective_factors`, `rationale` and `mitigation_plan` are
 * `SecondaryEncrypted` (the JSON fields as `json_encode`d strings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('safeguarding_cases');
            $table->timestamp('assessed_at');
            $table->foreignId('assessed_by')->constrained('users');
            $table->text('risk_factors');
            $table->text('protective_factors')->nullable();
            $table->string('risk_level', 20);
            $table->text('rationale');
            $table->text('mitigation_plan');
            $table->date('review_due_on');

            $table->index(['case_id', 'assessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
