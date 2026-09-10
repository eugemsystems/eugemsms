<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2/§3 ⭐/BR-OPS-03-006. `stock_lot_id` is the real
 * `FIN-09` lot the harvest creates in the farm store at cost per kg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvests', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_cycle_id')->constrained('crop_cycles');
            $table->date('harvested_on');
            $table->decimal('quantity_kg', 12, 2);
            $table->string('quality_grade', 20)->nullable();
            $table->decimal('moisture_percent', 5, 2)->nullable();
            $table->bigInteger('unit_cost_minor');
            $table->char('currency', 3);
            $table->string('destination', 20);
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->foreignId('stock_lot_id')->nullable()->constrained('stock_lots');
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->foreignId('recorded_by')->constrained('users');

            $table->index(['school_id', 'crop_cycle_id'], 'harvests_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvests');
    }
};
