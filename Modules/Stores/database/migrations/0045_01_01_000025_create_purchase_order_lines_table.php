<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/§3. `is_capital` is the same kind of boundary flag
 * as `FIN-09`'s `inventory_items.is_capitalisable` — a capital line is
 * still received and invoiced normally here; the actual `FIN-10` asset
 * creation is a real, fired event (`CapitalPurchaseReceived`),
 * deliberately not built out since `FIN-10` doesn't exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->smallInteger('line_number');
            $table->foreignId('item_id')->nullable()->constrained('inventory_items');
            $table->string('description', 500);
            $table->decimal('quantity_ordered', 14, 4);
            $table->decimal('quantity_received', 14, 4)->default(0);
            $table->decimal('quantity_rejected', 14, 4)->default(0);
            $table->decimal('quantity_invoiced', 14, 4)->default(0);
            $table->string('unit', 20);
            $table->bigInteger('unit_price_minor');
            $table->decimal('tax_rate_percent', 5, 2)->default(0);
            $table->string('tax_category', 20);
            $table->bigInteger('line_total_minor');
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts');
            $table->boolean('is_capital')->default(false);
            $table->foreignId('store_id')->nullable()->constrained('stores');

            $table->index(['purchase_order_id'], 'order_lines_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
