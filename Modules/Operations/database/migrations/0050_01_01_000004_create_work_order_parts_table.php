<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-02 §2/§3 ⭐/BR-OPS-02-005 — the real `FIN-09` linkage:
 * `store_requisition_id` points at a genuine `StoreRequisition` row
 * created against the work order's own cost centre, not a shadow
 * quantity/cost pair kept independently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders');
            $table->foreignId('item_id')->nullable()->constrained('inventory_items');
            $table->string('description', 255);
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->string('source', 20);
            $table->foreignId('store_requisition_id')->nullable()->constrained('store_requisitions');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->bigInteger('unit_cost_minor')->nullable();
            $table->bigInteger('line_cost_minor')->nullable();
            $table->timestamp('issued_at')->nullable();

            $table->index(['work_order_id'], 'work_order_parts_wo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_parts');
    }
};
