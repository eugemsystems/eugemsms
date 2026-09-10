<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-04 §2. Created before `menu_days` (which references
 * recipe ids inside its own JSON column, not a FK) purely to keep
 * `recipe_ingredients` right after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('category', 30);
            $table->smallInteger('base_servings');
            $table->text('preparation_notes')->nullable();
            $table->json('allergen_flags')->nullable();
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_halal_suitable')->default(true);
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code'], 'recipes_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
