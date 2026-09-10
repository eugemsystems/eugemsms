<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('supplier_invoices');
            $table->foreignId('po_line_id')->nullable()->constrained('purchase_order_lines');
            $table->foreignId('grn_line_id')->nullable()->constrained('grn_lines');
            $table->string('description', 500);
            $table->decimal('quantity', 14, 4);
            $table->bigInteger('unit_price_minor');
            $table->string('tax_category', 20);
            $table->decimal('tax_rate_percent', 5, 2)->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->foreignId('expense_account_id')->constrained('accounts');
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres');

            $table->index(['invoice_id'], 'invoice_lines_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_lines');
    }
};
