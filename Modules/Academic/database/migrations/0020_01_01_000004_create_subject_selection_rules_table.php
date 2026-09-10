<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-01 §3 ⭐/BR-ACA-01-007. Every subject-count constraint is a
 * row here — no count is hard-coded anywhere in the codebase. `pathway`
 * is a plain code string (`ACADEMIC`/`VOCATIONAL`), matching how
 * `students.pathway` is already stored (Book C PPL-01) rather than the
 * spec's literal `pathways` FK table, which this pass does not build.
 * `subject_prerequisites`-backed rules (`rule_type = prerequisite`) are
 * out of scope for the same reason — no `subject_prerequisites` table
 * exists yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_selection_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('framework_id')->constrained('curriculum_frameworks');
            $table->foreignId('grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->string('pathway', 20)->nullable();
            $table->string('rule_type', 30);
            $table->foreignId('subject_group_id')->nullable()->constrained('subject_groups')->nullOnDelete();
            $table->json('subject_ids')->nullable();
            $table->smallInteger('min_count')->nullable();
            $table->smallInteger('max_count')->nullable();
            $table->string('severity', 20);
            $table->string('message', 255);
            $table->string('source_reference', 150)->nullable();
            $table->boolean('requires_confirmation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'framework_id', 'grade_level_id', 'is_active'], 'selection_rules_school_framework_level_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_selection_rules');
    }
};
