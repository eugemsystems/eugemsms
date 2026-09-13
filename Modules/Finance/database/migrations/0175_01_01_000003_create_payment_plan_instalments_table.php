<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §2/BR-FIN-03-017. `paid_minor` is a cache updated as
 * FIN-04 receipts allocate against the plan's covered invoices — the
 * same "cache derived from allocations" pattern `invoices.paid_minor`
 * already uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plan_instalments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('payment_plans')->cascadeOnDelete();
            $table->tinyInteger('instalment_number');
            $table->date('due_date');
            $table->bigInteger('amount_minor');
            $table->bigInteger('paid_minor')->default(0);
            $table->string('status', 20);

            $table->unique(['plan_id', 'instalment_number']);
            $table->index(['plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_instalments');
    }
};
