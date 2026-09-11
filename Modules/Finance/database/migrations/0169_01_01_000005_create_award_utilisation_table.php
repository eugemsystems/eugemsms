<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K FIN-07 §2/BR-FIN-07-013 — APPEND-ONLY; one row per term this
 * award billed. Created only once its `fee_line_id`/`journal_id` are
 * both known (i.e. at invoice-issue time, not at billing-preview
 * time) — see `Modules\Finance\Models\AwardDiscountCommitment` for
 * how a discount computed during `ComputeBillingRunAction` survives
 * to become this row once `IssueInvoicesForAssignmentAction` actually
 * posts it. Model-level append-only guard, same pattern as
 * `journals`/`safeguarding_audit` — see either's own migration
 * docblock for why this is a model-level guard rather than a DB grant
 * REVOKE in this pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('award_utilisation', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('award_id')->constrained('discount_awards')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('component_id')->constrained('fee_components');
            $table->bigInteger('discount_minor');
            $table->char('currency', 3);
            $table->foreignId('fee_line_id')->constrained('learner_fee_lines');
            $table->foreignId('journal_id')->constrained();
            $table->timestamp('posted_at');

            $table->index(['school_id', 'award_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('award_utilisation');
    }
};
