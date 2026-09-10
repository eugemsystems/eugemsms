<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-14 §2/BR-FIN-14-002 — append-only, same guard shape as
 * this book's own `Modules\Fiscal`'s `fiscal_audit_log` and Book H2's
 * `OccurrenceBookEntry`. The spec's own note — "DB grants: INSERT,
 * SELECT only" — is enforced at the model layer here (an
 * `updating`/`deleting` guard), the same level every other
 * append-only table in this codebase is enforced at; no DB user grant
 * revocation exists in any migration in this codebase yet, so this
 * doesn't diverge from that established practice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('wallet_id')->constrained('student_wallets');
            $table->string('transaction_type', 30);
            $table->char('direction', 3);
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor');
            $table->char('currency', 3);
            $table->foreignId('spend_point_id')->nullable()->constrained('spend_points');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts');
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->string('reference', 120)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamp('occurred_at', 6);

            $table->index(['school_id', 'wallet_id', 'occurred_at'], 'wallet_transactions_school_wallet_idx');
            $table->index(['school_id', 'term_id', 'transaction_type'], 'wallet_transactions_school_term_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
