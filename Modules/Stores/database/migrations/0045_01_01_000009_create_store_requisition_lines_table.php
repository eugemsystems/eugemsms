<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_requisition_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained('store_requisitions');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('quantity_requested', 14, 4);
            $table->decimal('quantity_approved', 14, 4)->nullable();
            $table->decimal('quantity_issued', 14, 4)->nullable();
            $table->decimal('quantity_returned', 14, 4)->nullable();
            $table->string('unit', 20);
            $table->bigInteger('unit_cost_minor')->nullable();
            $table->bigInteger('line_cost_minor')->nullable();
            $table->foreignId('substituted_item_id')->nullable()->constrained('inventory_items');
            $table->string('substitution_note', 255)->nullable();
            $table->string('notes', 255)->nullable();

            $table->index(['requisition_id'], 'store_requisition_lines_req_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_requisition_lines');
    }
};
