<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-14 §2/§3 ⭐/BR-FIN-14-001. `balance_minor` is a cache —
 * `wallet_transactions` (append-only) is the source of truth, and
 * this cache is verified against it nightly (`ReconcileWalletLiabilityAction`,
 * BR-FIN-14-002/019). `liability_account_id` is a real FK: a wallet
 * balance is the school holding the parent's money, never income.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_wallets', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->bigInteger('balance_minor')->default(0);
            $table->char('currency', 3);
            $table->foreignId('liability_account_id')->constrained('accounts');
            $table->string('status', 20);
            $table->bigInteger('daily_limit_minor')->nullable();
            $table->bigInteger('weekly_limit_minor')->nullable();
            $table->bigInteger('per_transaction_limit_minor')->nullable();
            $table->json('blocked_categories')->nullable();
            $table->bigInteger('low_balance_threshold_minor')->nullable();
            $table->boolean('auto_topup_enabled')->default(false);
            $table->bigInteger('auto_topup_amount_minor')->nullable();
            $table->foreignId('controls_set_by')->nullable()->constrained('guardians');
            $table->timestamp('controls_updated_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();

            $table->unique(['school_id', 'student_id'], 'student_wallets_school_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_wallets');
    }
};
