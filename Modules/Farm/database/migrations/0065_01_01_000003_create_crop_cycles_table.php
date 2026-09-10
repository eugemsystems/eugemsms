<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2 ⭐/BR-OPS-03-005/007. `cost_per_kg_minor` is the
 * internal transfer price — derived, never entered directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_cycles', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('production_unit_id')->constrained('production_units');
            $table->foreignId('field_id')->constrained('fields');
            $table->string('cycle_reference', 40);
            $table->string('crop', 120);
            $table->string('variety', 120)->nullable();
            $table->string('season', 30);
            $table->decimal('area_planted_hectares', 10, 4);
            $table->date('planted_on')->nullable();
            $table->date('expected_harvest_on')->nullable();
            $table->date('actual_harvest_on')->nullable();
            $table->decimal('expected_yield_kg', 12, 2)->nullable();
            $table->decimal('actual_yield_kg', 12, 2)->nullable();
            $table->bigInteger('input_cost_minor')->default(0);
            $table->bigInteger('labour_cost_minor')->default(0);
            $table->bigInteger('overhead_cost_minor')->default(0);
            $table->bigInteger('total_cost_minor')->default(0);
            $table->bigInteger('cost_per_kg_minor')->nullable();
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->string('failure_reason', 255)->nullable();

            $table->unique(['school_id', 'cycle_reference'], 'crop_cycles_school_reference_unique');
            $table->index(['school_id', 'production_unit_id', 'status'], 'crop_cycles_unit_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_cycles');
    }
};
