<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-14 §2/§4/BR-FIN-14-010/011. `fiscal_receipt_id` is a
 * real FK into `Modules\Fiscal`'s `fiscal_receipts` (FIN-13, already
 * built this book) — `ProcessWalletSaleAction` routes for real, no
 * forward-reference placeholder needed here. `offline_reference` is
 * the offline-sync idempotency key (BR-FIN-14-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_sales', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('spend_point_id')->constrained('spend_points');
            $table->string('sale_number', 40);
            $table->foreignId('student_id')->nullable()->constrained();
            $table->foreignId('wallet_id')->nullable()->constrained('student_wallets');
            $table->timestamp('sold_at');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->char('currency', 3);
            $table->string('payment_method', 20);
            $table->string('identification_method', 20)->nullable();
            $table->bigInteger('cost_of_sales_minor')->nullable();
            $table->foreignId('operator_id')->constrained('users');
            $table->foreignId('till_session_id')->nullable()->constrained('till_sessions');
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->foreignId('fiscal_receipt_id')->nullable()->constrained('fiscal_receipts');
            $table->string('device_source', 20);
            $table->char('offline_reference', 36)->nullable();
            $table->string('status', 20);

            $table->unique(['school_id', 'sale_number'], 'wallet_sales_school_number_unique');
            $table->unique(['school_id', 'offline_reference'], 'wallet_sales_school_offline_ref_unique');
            $table->index(['school_id', 'spend_point_id', 'sold_at'], 'wallet_sales_school_point_sold_idx');
            $table->index(['school_id', 'student_id', 'sold_at'], 'wallet_sales_school_student_sold_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_sales');
    }
};
