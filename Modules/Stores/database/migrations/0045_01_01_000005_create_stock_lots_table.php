<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2/§4 ⭐ — FIFO layers. `quantity_in` (`quantity_received`)
 * is append-only in spirit: once created, a lot's `quantity_remaining`
 * only ever decreases via `IssueStockAction`'s own row-locked update,
 * never a direct edit. `supplier_id` is a forward reference to
 * `FIN-08`, no FK yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->string('lot_reference', 60);
            $table->string('batch_number', 60)->nullable();
            $table->date('received_on');
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity_received', 14, 4);
            $table->decimal('quantity_remaining', 14, 4);
            $table->bigInteger('unit_cost_minor');
            $table->string('currency', 3);
            $table->bigInteger('base_unit_cost_minor');
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates');
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->boolean('is_depleted')->default(false);

            $table->index(['school_id', 'store_id', 'item_id', 'is_depleted', 'received_on'], 'stock_lots_fifo_idx');
            $table->index(['school_id', 'expiry_date', 'is_depleted'], 'stock_lots_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
