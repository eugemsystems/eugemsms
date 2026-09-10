<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2/BR-OPS-01-011/012 ⭐. `fixed_asset_id` is a real
 * FK to `FIN-10`'s asset register and `maintenance_asset_id` a real
 * FK to `OPS-02`'s own register (both already exist). `assigned_driver_id`
 * is a plain forward reference to `drivers`, created later in this
 * same migration set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('fleet_number', 20);
            $table->string('registration_number', 20);
            $table->string('vehicle_type', 30);
            $table->string('make', 60)->nullable();
            $table->string('model', 80)->nullable();
            $table->smallInteger('year_of_manufacture')->nullable();
            $table->string('chassis_number', 60)->nullable();
            $table->string('engine_number', 60)->nullable();
            $table->smallInteger('seating_capacity');
            $table->smallInteger('standing_capacity')->default(0);
            $table->string('fuel_type', 20);
            $table->decimal('tank_capacity_litres', 8, 2)->nullable();
            $table->decimal('expected_km_per_litre', 6, 2)->nullable();
            $table->decimal('current_odometer_km', 12, 2)->default(0);
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets');
            $table->foreignId('maintenance_asset_id')->nullable()->constrained('maintenance_assets');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->unsignedBigInteger('assigned_driver_id')->nullable();
            $table->string('status', 20);
            $table->string('grounded_reason', 255)->nullable();
            $table->string('tracker_device_id', 80)->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'fleet_number'], 'vehicles_school_fleet_unique');
            $table->unique(['school_id', 'registration_number'], 'vehicles_school_reg_unique');
            $table->index(['school_id', 'status'], 'vehicles_school_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
