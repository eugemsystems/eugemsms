<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2 — not itemised in the spec's own transfer header
 * table, but a transfer moving more than one item needs line-level
 * quantities; added the same way this codebase has repeatedly added a
 * column/table a later business rule needs beyond the spec's own §2
 * listing (documented inline each time it happens).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_id')->constrained('stock_transfers');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('quantity_dispatched', 14, 4);
            $table->decimal('quantity_received', 14, 4)->nullable();
            $table->bigInteger('unit_cost_minor');
            $table->bigInteger('line_cost_minor');
            $table->string('currency', 3);

            $table->index(['transfer_id'], 'stock_transfer_lines_transfer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
    }
};
