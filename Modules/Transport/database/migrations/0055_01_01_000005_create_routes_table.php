<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2/BR-OPS-01-007.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('direction', 20);
            $table->foreignId('assigned_vehicle_id')->nullable()->constrained('vehicles');
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers');
            $table->foreignId('assistant_staff_id')->nullable()->constrained('staff');
            $table->decimal('total_distance_km', 8, 2)->nullable();
            $table->smallInteger('estimated_duration_min')->nullable();
            $table->time('departure_time')->nullable();
            $table->smallInteger('capacity');
            $table->smallInteger('current_passengers')->default(0);
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'academic_year_id', 'code'], 'routes_school_year_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
