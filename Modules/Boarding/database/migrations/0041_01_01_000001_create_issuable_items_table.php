<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-05 §2. `inventory_item_id` is a forward reference to
 * `FIN-09` (Book H, not built).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issuable_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('category', 30);
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->boolean('is_returnable')->default(true);
            $table->boolean('is_launderable')->default(true);
            $table->bigInteger('replacement_cost_minor')->nullable();
            $table->char('currency', 3);
            $table->smallInteger('expected_lifespan_terms')->nullable();
            $table->boolean('requires_tagging')->default(true);

            $table->unique(['school_id', 'code'], 'issuable_items_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issuable_items');
    }
};
