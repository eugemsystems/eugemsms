<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-019/020. `batch_id` groups payments made
 * in the same payment run for one remittance advice — a plain
 * self-referencing grouping id (the first payment's own id), not a
 * separate table, matching how simple grouping is handled elsewhere
 * in this codebase where a dedicated batch table isn't warranted yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('payment_number', 40);
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->date('payment_date');
            $table->string('payment_method', 30);
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts');
            $table->string('reference', 120)->nullable();
            $table->bigInteger('gross_minor');
            $table->bigInteger('withholding_minor')->default(0);
            $table->bigInteger('net_minor');
            $table->string('currency', 3);
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates');
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->unsignedBigInteger('remittance_document_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'payment_number']);
            $table->index(['school_id', 'batch_id'], 'payments_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
