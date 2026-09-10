<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-02 §2. The computed result of one billing pass for one
 * learner in one term. `resolution_trace` (BR-FIN-02-003) is the
 * answer to every fee dispute the school will ever have — every
 * structure evaluated, matched or not, and why.
 * `invoice_id` is a plain reference with no FK constraint yet — `FIN-03`
 * (invoicing), which owns that table, doesn't exist in this pass. See
 * how `ACA-02`'s `learner_subject_enrolments.fee_line_id` deferred the
 * same way. `billing_run_id` is not in the spec's literal SQL — added
 * so `ApproveBillingRunAction`/`CommitBillingRunAction` can identify
 * exactly which assignments belong to which run instead of guessing
 * from a timestamp window; its FK constraint is added once
 * `billing_runs` exists later in this same migration batch (mirrors
 * how FIN-06 added `journal_lines.exchange_rate_id`'s FK only once
 * `exchange_rates` existed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_fee_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('structure_id')->constrained('fee_structures');
            $table->smallInteger('structure_version');
            $table->json('resolution_trace');
            $table->timestamp('computed_at');
            $table->foreignId('computed_by')->constrained('users');
            $table->string('status', 20);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('billing_run_id')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'term_id', 'status'], 'learner_fee_assignments_term_status_idx');
            $table->index(['school_id', 'term_id', 'student_id']);
            $table->index('billing_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_fee_assignments');
    }
};
