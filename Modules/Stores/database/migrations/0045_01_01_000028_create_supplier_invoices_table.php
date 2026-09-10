<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/§3/§4/§5 ⭐. `input_vat_claimable` is derived at
 * registration (vat-registered supplier AND fiscal AND standard-rated)
 * and stored, not recomputed on every read — the same "derived once,
 * stored, never silently drifts" doctrine as other computed flags in
 * this codebase. `paid_minor`/`balance_minor` are caches maintained by
 * `RecordSupplierPaymentAction`'s own allocations, verified the same
 * "never the sole source of truth" way every other cache in this
 * codebase is (the GL itself is authoritative).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('invoice_number', 60);
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->date('invoice_date');
            $table->date('received_on');
            $table->date('due_date');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->string('currency', 3);
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates');
            $table->bigInteger('base_total_minor');
            $table->boolean('is_fiscal_invoice')->default(false);
            $table->string('fiscal_device_id', 60)->nullable();
            $table->string('fiscal_verification_code', 80)->nullable();
            $table->boolean('fiscal_qr_verified')->default(false);
            $table->boolean('input_vat_claimable')->default(false);
            $table->bigInteger('input_vat_minor')->nullable();
            $table->boolean('withholding_applied')->default(false);
            $table->decimal('withholding_rate_percent', 5, 2)->nullable();
            $table->bigInteger('withholding_minor')->default(0);
            $table->string('withholding_reason', 120)->nullable();
            $table->bigInteger('net_payable_minor');
            $table->string('match_status', 20);
            $table->bigInteger('match_variance_minor')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->bigInteger('paid_minor')->default(0);
            $table->bigInteger('balance_minor');
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'supplier_id', 'invoice_number']);
            $table->index(['school_id', 'status', 'due_date'], 'invoices_status_due_idx');
            $table->index(['school_id', 'supplier_id', 'status'], 'invoices_supplier_status_idx');
            $table->index(['school_id', 'is_fiscal_invoice', 'input_vat_claimable'], 'invoices_vat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
