<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-010/011 ⭐. `committed_minor` is written
 * once, on approval, and released as invoices match — the `FIN-11`
 * commitment ledger itself doesn't exist yet, so this column is only
 * ever a local snapshot today; `PurchaseOrderApproved` fires the real
 * event `FIN-11` will consume once it exists (the same
 * `ItemCapitalisationDue`-style deferral `FIN-09` used for `FIN-10`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('po_number', 40);
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('requisition_id')->nullable()->constrained('purchase_requisitions');
            $table->foreignId('quotation_id')->nullable()->constrained('quotations');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->unsignedBigInteger('budget_line_id')->nullable();
            $table->date('order_date');
            $table->date('expected_delivery')->nullable();
            $table->string('delivery_address', 255)->nullable();
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->string('currency', 3);
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates');
            $table->bigInteger('base_total_minor');
            $table->bigInteger('committed_minor');
            $table->bigInteger('released_minor')->default(0);
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'po_number']);
            $table->index(['school_id', 'supplier_id', 'status'], 'orders_supplier_status_idx');
            $table->index(['school_id', 'status', 'order_date'], 'orders_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
