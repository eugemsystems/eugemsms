<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-11 §2/§3 ⭐/BR-FIN-11-001/007/008. `committed_minor`/
 * `actual_minor`/`available_minor` are the live position — the whole
 * point of commitment accounting is that `available` drops the
 * instant a PO is approved, not when it is eventually paid.
 * `actual_minor` is a cache derived exclusively from `journal_lines`
 * (BR-FIN-11-008 ⭐) via `RecalculateBudgetLineActualsAction` — no
 * action in this module ever writes it directly from a manually
 * typed figure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained('budgets');
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->foreignId('term_id')->nullable()->constrained();
            $table->bigInteger('annual_amount_minor');
            $table->bigInteger('term_1_minor')->nullable();
            $table->bigInteger('term_2_minor')->nullable();
            $table->bigInteger('term_3_minor')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('committed_minor')->default(0);
            $table->bigInteger('actual_minor')->default(0);
            $table->bigInteger('available_minor')->default(0);
            $table->bigInteger('prior_year_actual_minor')->nullable();
            $table->string('basis_note', 500)->nullable();
            $table->boolean('is_locked')->default(false);

            $table->unique(['budget_id', 'account_id', 'cost_centre_id', 'term_id'], 'budget_lines_unique_line');
            $table->index(['school_id', 'cost_centre_id', 'available_minor'], 'budget_lines_cc_available_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
    }
};
