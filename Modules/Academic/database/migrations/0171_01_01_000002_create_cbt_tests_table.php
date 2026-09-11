<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-09 §2/BR-ACA-09-010 ⭐. `assessment_id` is this migration's
 * own addition on top of the spec's `assessment_type_id` — same
 * reasoning as `assignments.assessment_id` from `ACA-08`'s migration:
 * a concrete `Assessment` row is auto-provisioned once, at test
 * creation, so `PublishCbtResultsAction` never has to re-derive which
 * assessment a test's marks belong to. `question_ids` is populated for
 * BOTH assembly methods (not just `manual`, despite the spec's own
 * column comment) — delivery needs one concrete, fixed question set
 * to seed each candidate's own randomised order from
 * (BR-ACA-09-004), regardless of how that set was assembled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbt_tests', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('title', 200);
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('assessment_type_id')->nullable()->constrained('assessment_types');
            $table->foreignId('assessment_id')->nullable()->constrained('assessments');
            $table->string('assembly_method', 20);
            $table->json('assembly_rules')->nullable();
            $table->json('question_ids')->nullable();
            $table->boolean('randomise_question_order')->default(true);
            $table->boolean('randomise_option_order')->default(true);
            $table->smallInteger('duration_minutes');
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->boolean('browser_focus_monitoring')->default(false);
            $table->smallInteger('max_tab_switches')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'term_id', 'status'], 'cbt_tests_school_term_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_tests');
    }
};
