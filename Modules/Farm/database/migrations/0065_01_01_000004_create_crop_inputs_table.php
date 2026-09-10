<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-002 — real `FIN-09` requisitions
 * against the farm store, FIFO-costed by that same engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_inputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_cycle_id')->constrained('crop_cycles');
            $table->string('input_type', 30);
            $table->foreignId('item_id')->nullable()->constrained('inventory_items');
            $table->string('description', 200);
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->date('applied_on');
            $table->foreignId('store_requisition_id')->nullable()->constrained('store_requisitions');
            $table->bigInteger('cost_minor');
            $table->char('currency', 3);
            $table->foreignId('applied_by')->nullable()->constrained('users');

            $table->index(['school_id', 'crop_cycle_id'], 'crop_inputs_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_inputs');
    }
};
