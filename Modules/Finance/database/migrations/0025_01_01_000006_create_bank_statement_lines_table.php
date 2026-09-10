<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-05 §3/BR-FIN-05-011/012. `matched_type`/`matched_id` are
 * a plain polymorphic reference (no FK — the matched record is most
 * often a `Receipt`, but the shape is generic per the spec).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->integer('line_number');
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->string('description', 500);
            $table->string('reference', 150)->nullable();
            $table->bigInteger('debit_minor')->nullable();
            $table->bigInteger('credit_minor')->nullable();
            $table->bigInteger('running_balance_minor')->nullable();
            $table->char('currency', 3);
            $table->string('match_status', 20);
            $table->string('matched_type', 60)->nullable();
            $table->unsignedBigInteger('matched_id')->nullable();
            $table->tinyInteger('match_confidence')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();

            $table->index(['school_id', 'statement_id', 'match_status']);
            $table->index(['school_id', 'transaction_date', 'match_status'], 'bank_statement_lines_school_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
