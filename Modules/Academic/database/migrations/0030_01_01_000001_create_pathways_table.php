<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-01 §2 🇿🇼 — the two-route model (academic/vocational),
 * deferred from the module's first pass (built alongside
 * `learner_subject_enrolments`'s billing-critical path). This
 * completes ACA-01; `subject_selection_rules.pathway` keeps its
 * existing plain-string column rather than being migrated to a
 * `pathway_id` FK — see that table's own migration docblock for why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pathways', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('framework_id')->constrained('curriculum_frameworks');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->smallInteger('applies_from_level_ordinal');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'framework_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pathways');
    }
};
