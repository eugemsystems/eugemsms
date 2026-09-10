<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisition_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained('purchase_requisitions');
            $table->foreignId('item_id')->nullable()->constrained('inventory_items');
            $table->string('description', 500);
            $table->text('specification')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 20);
            $table->bigInteger('estimated_unit_minor')->nullable();
            $table->bigInteger('estimated_total_minor')->nullable();
            $table->string('currency', 3);
            $table->decimal('ordered_quantity', 14, 4)->default(0);

            $table->index(['requisition_id'], 'requisition_lines_requisition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_lines');
    }
};
