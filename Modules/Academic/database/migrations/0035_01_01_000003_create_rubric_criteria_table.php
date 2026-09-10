<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §2/BR-ACA-06-007 — weights within a rubric must total
 * 100%, validated by `CreateProjectRubricAction` at save time.
 * Deliberately no `school_id` of its own, matching the spec's literal
 * schema — always reached through its owning `project_rubrics` row,
 * same reasoning as `FeeStructureRule`/`CreditNoteLine` (Book B).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubric_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rubric_id')->constrained('project_rubrics')->cascadeOnDelete();
            $table->string('criterion', 200);
            $table->text('description')->nullable();
            $table->decimal('max_mark', 6, 2);
            $table->decimal('weight_percent', 5, 2);
            $table->json('performance_levels');
            $table->smallInteger('sort_order')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
    }
};
