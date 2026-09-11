<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K FIN-07 — a deliberate, documented extension beyond the
 * spec's own §2 table listing (see `AwardDiscountCommitment`'s own
 * docblock for the reasoning): `award_utilisation` cannot exist until
 * a journal is posted (it's `NOT NULL` on `journal_id`), but the
 * discount itself is computed and locked into the immutable
 * `learner_fee_lines.discount_minor` earlier, at billing-preview
 * time, by `AwardDiscountResolver`. A fee line's total discount can
 * legitimately come from more than one concurrently-active award —
 * each potentially posting to a different scheme's own contra
 * account — so this row is what lets `IssueInvoicesForAssignmentAction`
 * later reconstruct exactly which award(s) contributed how much to
 * that already-fixed total, without re-running the resolver (which
 * could legitimately return a different answer if circumstances
 * changed between preview and invoice) and without ever mutating the
 * immutable fee line itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('award_discount_commitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('fee_line_id')->constrained('learner_fee_lines')->cascadeOnDelete();
            $table->foreignId('award_id')->constrained('discount_awards');
            $table->foreignId('scheme_id')->constrained('discount_schemes');
            $table->bigInteger('discount_minor');
            $table->char('currency', 3);
            $table->timestamps();

            $table->index(['school_id', 'fee_line_id'], 'award_discount_commitments_school_fee_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('award_discount_commitments');
    }
};
