<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §3 ⭐ — the abstraction: SBP is the current concrete
 * instrument, CALA is preserved read-only. `ContinuousAssessmentProvider`
 * (§4) picks between them per `curriculum_frameworks.continuous_assessment_model`,
 * never a hard-coded branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_instruments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('framework_id')->constrained('curriculum_frameworks');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->tinyInteger('projects_per_subject_per_year')->default(1);
            $table->boolean('applies_to_exam_classes')->default(true);
            $table->boolean('contributes_to_final_mark')->default(true);
            $table->decimal('default_weight_percent', 5, 2)->nullable();
            $table->boolean('is_readonly')->default(false);
            $table->string('reference_circular', 120)->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['school_id', 'framework_id', 'code'], 'assessment_instruments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_instruments');
    }
};
