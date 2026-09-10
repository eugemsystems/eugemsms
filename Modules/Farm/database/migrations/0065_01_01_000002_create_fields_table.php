<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-018. `water_source_id` is a real FK
 * into `OPS-04`'s `water_sources` (already exists).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_unit_id')->constrained('production_units');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->decimal('area_hectares', 10, 4);
            $table->string('soil_type', 60)->nullable();
            $table->boolean('is_irrigated')->default(false);
            $table->string('irrigation_type', 30)->nullable();
            $table->foreignId('water_source_id')->nullable()->constrained('water_sources');
            $table->date('last_soil_test_on')->nullable();
            $table->text('notes')->nullable();

            $table->unique(['school_id', 'code'], 'fields_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
