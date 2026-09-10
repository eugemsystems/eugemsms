<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-03 §3/BR-CORE-03-010, not itself in the spec's literal
 * schema block — CORE-03 §4 names `ACT-ReopenPeriod`'s "two-approver
 * flow through CORE-07", but CORE-07 (Workflow & Approvals) is built
 * long after CORE-03 in the module order. `period_state_transitions` is
 * append-only (BR-CORE-03-009) and so cannot itself hold a row that
 * starts unapproved and is later updated with an approver — this table
 * is the minimum needed to make "the period remains LOCKED until a
 * *different* user approves" (AC-CORE-03-002) a real, working guarantee
 * now rather than a TODO. `ACT-ReopenPeriod` writes exactly one
 * `period_state_transitions` row once a request here reaches `approved`,
 * mirroring `approved_by` from this table onto that one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_reopen_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 10);
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['school_id', 'term_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_reopen_requests');
    }
};
