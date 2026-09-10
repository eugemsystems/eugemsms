<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2 ⭐/BR-OPS-01-009/011. `manifest_document_id` is a
 * plain nullable column — no generated-document store exists yet for
 * this module to attach a real FK to. Distance is always computed
 * from `departure_odometer`/`return_odometer` (BR-OPS-01-011), never
 * accepted as direct input — see `RecordTripOdometerAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->date('trip_date');
            $table->string('trip_type', 20);
            $table->foreignId('route_id')->nullable()->constrained('routes');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('driver_id')->constrained('drivers');
            $table->foreignId('escort_staff_id')->nullable()->constrained('staff');
            $table->string('purpose', 255)->nullable();
            $table->string('destination', 200)->nullable();
            $table->decimal('departure_odometer', 12, 2)->nullable();
            $table->decimal('return_odometer', 12, 2)->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->smallInteger('passenger_count')->nullable();
            $table->string('status', 20);
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('manifest_document_id')->nullable();

            $table->index(['school_id', 'trip_date', 'status'], 'trips_date_status_idx');
            $table->index(['school_id', 'vehicle_id', 'trip_date'], 'trips_vehicle_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
