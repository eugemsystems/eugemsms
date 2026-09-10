<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-014 ⭐ — `system_quantity` is hidden
 * from the counter until their count is submitted; enforced at the
 * Action layer (the count-sheet query never selects this column for
 * the counting screen), not by a DB constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_take_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_take_id')->constrained('stock_takes');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('system_quantity', 14, 4);
            $table->decimal('counted_quantity', 14, 4)->nullable();
            $table->decimal('recount_quantity', 14, 4)->nullable();
            $table->decimal('variance_quantity', 14, 4)->nullable();
            $table->bigInteger('variance_value_minor')->nullable();
            $table->decimal('variance_percent', 6, 2)->nullable();
            $table->string('variance_reason', 255)->nullable();
            $table->boolean('requires_recount')->default(false);
            $table->foreignId('counted_by')->nullable()->constrained('users');
            $table->timestamp('counted_at')->nullable();

            $table->index(['stock_take_id'], 'stock_take_lines_take_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_lines');
    }
};
