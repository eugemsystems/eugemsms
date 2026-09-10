<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-01 §2/BR-FIN-01-011. Append-only, no exceptions — enforced
 * at the model level (see `journals` migration's note).
 *
 * `exchange_rate_id` has no FK constraint yet: `exchange_rates` is
 * FIN-06, built directly after this module. The constraint is added by
 * a FIN-06 migration once that table exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('line_number');
            $table->foreignId('account_id')->constrained();
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres');
            $table->char('direction', 2);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->bigInteger('base_amount_minor');
            $table->char('base_currency', 3);
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->unsignedBigInteger('exchange_rate_id')->nullable();
            $table->string('subledger_type', 30)->nullable();
            $table->unsignedBigInteger('subledger_id')->nullable();
            $table->string('narration', 255)->nullable();
            $table->date('effective_at');
            $table->foreignId('term_id')->constrained();
            $table->timestamp('created_at');

            $table->index(['school_id', 'account_id', 'effective_at']);
            $table->index(['school_id', 'subledger_type', 'subledger_id', 'effective_at'], 'journal_lines_school_subledger_effective_idx');
            $table->index(['school_id', 'term_id', 'account_id']);
            $table->index(['journal_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
