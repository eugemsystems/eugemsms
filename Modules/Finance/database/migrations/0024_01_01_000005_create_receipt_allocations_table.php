<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-04 §2/§4/BR-FIN-04-016. `invoice_id = null` represents an
 * overpayment credit balance (`allocation_method = 'credit_balance'`)
 * rather than a settlement against a specific invoice. Reallocation
 * reverses a row (`reversed_at`/`reversed_by`/`reversal_reason`) and
 * creates a new one — it never edits or deletes the original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained('invoice_lines')->nullOnDelete();
            $table->foreignId('component_id')->nullable()->constrained('fee_components')->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('allocation_method', 20);
            $table->timestamp('allocated_at');
            $table->foreignId('allocated_by')->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->index(['school_id', 'invoice_id']);
            $table->index(['school_id', 'receipt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
    }
};
