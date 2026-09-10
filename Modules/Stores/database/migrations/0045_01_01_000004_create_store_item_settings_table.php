<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2 — per item per store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_item_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('reorder_level', 14, 4)->nullable();
            $table->decimal('reorder_quantity', 14, 4)->nullable();
            $table->decimal('maximum_level', 14, 4)->nullable();
            $table->string('bin_location', 60)->nullable();
            $table->boolean('is_stocked')->default(true);

            $table->unique(['store_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_item_settings');
    }
};
