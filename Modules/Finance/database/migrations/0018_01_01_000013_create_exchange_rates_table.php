<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-06 §2/BR-FIN-06-004. Append-only — correcting a rate
 * supersedes it (sets `effective_to`, `status = superseded`) and
 * inserts a new row; never edited in place. Real enforcement is a
 * model-level guard restricting `UPDATE` to `effective_to`, `status`,
 * `approved_by`, `approved_at` — see `journals`' migration (FIN-01) for
 * why the DB-trigger version of this control is a deployment step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('exchange_rate_sources');
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->decimal('rate', 20, 10);
            $table->decimal('inverse_rate', 20, 10);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->string('status', 20);
            $table->foreignId('captured_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at');

            $table->index(['school_id', 'from_currency', 'to_currency', 'effective_from'], 'exchange_rates_pair_effective_idx');
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
