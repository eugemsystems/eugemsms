<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-01 §2/BR-ACA-01-006/010/011 — which subjects exist at
 * which level, per academic year. `is_compulsory` is what
 * `min_compulsory` rules and class-allocation auto-enrolment
 * (`ACA-02`) read; `option_block` is what `one_per_option_block`
 * enforces. Both `SubjectSelectionRuleEngine` rule types were
 * skipped as unsupported until this table existed — see that
 * class's own docblock, updated alongside this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_subject_offerings', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('pathway_id')->nullable()->constrained('pathways')->nullOnDelete();
            $table->boolean('is_compulsory')->default(false);
            $table->boolean('is_available')->default(true);
            $table->smallInteger('periods_per_week')->nullable();
            $table->smallInteger('max_learners')->nullable();
            $table->string('option_block', 20)->nullable();
            $table->smallInteger('sort_order')->nullable();

            $table->unique(['school_id', 'academic_year_id', 'grade_level_id', 'subject_id', 'pathway_id'], 'level_subject_offerings_unique');
            $table->index(['school_id', 'academic_year_id', 'grade_level_id', 'is_compulsory'], 'level_subject_offerings_compulsory_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_subject_offerings');
    }
};
