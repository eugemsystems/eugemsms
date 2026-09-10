<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-01 §2/BR-CMP-01-002/003. Field-level candidate
 * validation is data, not code, because ZIMSEC's requirements change
 * between series. Subject-count/pathway validation is NOT duplicated
 * here — `ValidateZimsecCandidatesAction` reuses ACA-01's own
 * `SubjectSelectionRuleEngine` for that (BR-CMP-01-005), so this
 * table's own `field` values are the candidate-bio-data ones:
 * surname, forenames, date_of_birth, gender, national_registration_no,
 * subject_count is deliberately excluded — see the reuse above.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zimsec_validation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('exam_level', 20)->nullable();
            $table->string('field', 60);
            $table->string('rule_type', 30);
            $table->string('rule_value', 255)->nullable();
            $table->string('severity', 10);
            $table->string('message', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'exam_level', 'is_active'], 'zimsec_validation_rules_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zimsec_validation_rules');
    }
};
