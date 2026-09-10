<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-04 §2. `recipe_ids` is a JSON list, not a pivot — a
 * cycle day's menu is small and always read/written together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('menu_cycles');
            $table->tinyInteger('cycle_day');
            $table->string('meal', 20);
            $table->json('recipe_ids');
            $table->string('notes', 255)->nullable();

            $table->unique(['cycle_id', 'cycle_day', 'meal'], 'menu_days_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_days');
    }
};
