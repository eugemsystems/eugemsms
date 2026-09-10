<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('supplier_payments');
            $table->foreignId('invoice_id')->constrained('supplier_invoices');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->timestamp('allocated_at');
            $table->foreignId('allocated_by')->constrained('users');

            $table->index(['payment_id'], 'payment_allocations_payment_idx');
            $table->index(['invoice_id'], 'payment_allocations_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
    }
};
