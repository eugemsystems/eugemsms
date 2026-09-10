<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-04 §2/§3 ⭐. `inventory_item_id` is a forward reference
 * to `FIN-09` (Book H, not built) — no FK, matching this session's
 * established forward-reference precedent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes');
            $table->unsignedBigInteger('inventory_item_id');
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->boolean('is_substitutable')->default(false);
            $table->json('substitute_item_ids')->nullable();
            $table->decimal('wastage_allowance_pct', 5, 2)->default(0);

            $table->index(['school_id', 'recipe_id'], 'recipe_ingredients_recipe_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
