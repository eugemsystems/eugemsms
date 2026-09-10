<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-022 ⭐ — anomaly detection baseline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_baselines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->string('period_type', 20);
            $table->decimal('expected_quantity', 14, 4);
            $table->decimal('tolerance_percent', 5, 2)->default(15);
            $table->smallInteger('computed_from_days');
            $table->timestamp('last_computed_at')->nullable();

            $table->unique(['store_id', 'item_id', 'period_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_baselines');
    }
};
