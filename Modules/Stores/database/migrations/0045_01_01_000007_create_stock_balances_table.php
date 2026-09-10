<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-002 — CACHE ONLY, rebuilt from
 * `stock_movements`. Nothing writes to it directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('quantity_on_hand', 14, 4)->default(0);
            $table->decimal('quantity_committed', 14, 4)->default(0);
            $table->decimal('quantity_available', 14, 4)->default(0);
            $table->bigInteger('value_minor')->default(0);
            $table->string('currency', 3);
            $table->bigInteger('average_unit_cost_minor')->nullable();
            $table->unsignedBigInteger('last_movement_id')->nullable();
            $table->date('last_received_on')->nullable();
            $table->date('last_issued_on')->nullable();
            $table->timestamp('rebuilt_at')->nullable();

            $table->unique(['school_id', 'store_id', 'item_id']);
            $table->index(['school_id', 'store_id', 'quantity_on_hand'], 'stock_balances_onhand_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
