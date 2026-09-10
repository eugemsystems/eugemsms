<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-04 §2/§3 ⭐ — what the kitchen draws, computed from
 * recipes × servings. `unit_cost_minor`/`line_cost_minor` stay null
 * in planning-only mode (§0.3/§4's `StoreIssuanceProvider` — no
 * `FIN-09`) rather than ever showing as zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_requisition_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_service_id')->constrained('meal_services');
            $table->unsignedBigInteger('inventory_item_id');
            $table->decimal('required_quantity', 12, 4);
            $table->decimal('issued_quantity', 12, 4)->nullable();
            $table->decimal('returned_quantity', 12, 4)->nullable();
            $table->decimal('wasted_quantity', 12, 4)->nullable();
            $table->string('unit', 20);
            $table->bigInteger('unit_cost_minor')->nullable();
            $table->bigInteger('line_cost_minor')->nullable();
            $table->string('substitution_note', 255)->nullable();

            $table->index(['school_id', 'meal_service_id'], 'meal_requisition_lines_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_requisition_lines');
    }
};
