<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-03 §5. "Module registration, seeded from code" — mirrors
 * `SeedPackRegistry`'s pattern: modules register themselves in their own
 * service provider's `boot()`, and this table is a queryable mirror of
 * that in-code registry (`RolloverHandlerRegistry::syncToDatabase()`),
 * not a hand-edited configuration surface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollover_handlers', function (Blueprint $table): void {
            $table->id();
            $table->string('module_code', 20);
            $table->string('handler_class', 255);
            $table->smallInteger('sort_order');
            $table->boolean('is_blocking')->default(true);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique('handler_class');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rollover_handlers');
    }
};
