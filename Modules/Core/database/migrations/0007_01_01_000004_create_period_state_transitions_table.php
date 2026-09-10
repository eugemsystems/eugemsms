<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-03 §2/BR-CORE-03-009. Append-only: every `ACT-TransitionPeriodState`
 * call writes exactly one row here, and nothing ever updates or deletes
 * one — no `updated_at`, no soft deletes, and `UPDATE`/`DELETE` are
 * revoked for the app DB user at the database grant level (Volume 1
 * "financial and safeguarding tables are append-only", CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_state_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 10);
            $table->string('from_state', 20);
            $table->string('to_state', 20);
            $table->text('reason')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at');

            $table->index(['school_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_state_transitions');
    }
};
