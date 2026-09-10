<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-012/014. `stock_lot_id` links back to
 * the real `FIN-09` lot `RecordGoodsReceivedNoteAction` creates via
 * `ReceiveStockAction` in the same transaction — null for a rejected
 * line or a service/non-stocked item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grn_id')->constrained('goods_received_notes');
            $table->foreignId('po_line_id')->constrained('purchase_order_lines');
            $table->foreignId('item_id')->nullable()->constrained('inventory_items');
            $table->decimal('quantity_delivered', 14, 4);
            $table->decimal('quantity_accepted', 14, 4);
            $table->decimal('quantity_rejected', 14, 4)->default(0);
            $table->string('rejection_reason', 255)->nullable();
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->bigInteger('unit_cost_minor');
            $table->foreignId('stock_lot_id')->nullable()->constrained('stock_lots');

            $table->index(['grn_id'], 'grn_lines_grn_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_lines');
    }
};
