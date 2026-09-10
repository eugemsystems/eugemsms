<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-04 §2/BR-FIN-04-018. A cheque tender is receipted with
 * `is_cleared = false`; `ClearChequeAction` is the only thing that
 * ever flips it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_tenders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->string('tender_type', 30);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reference', 120)->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->boolean('is_cleared')->default(true);

            $table->index('receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_tenders');
    }
};
