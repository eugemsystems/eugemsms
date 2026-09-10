<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2/BR-OPS-01-017. `work_order_id` is a real FK into
 * `OPS-02`'s `work_orders`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_incidents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->foreignId('trip_id')->nullable()->constrained('trips');
            $table->string('incident_type', 30);
            $table->timestamp('occurred_at');
            $table->string('location', 200);
            $table->text('description');
            $table->json('learners_involved')->nullable();
            $table->boolean('injuries')->default(false);
            $table->string('police_report_number', 60)->nullable();
            $table->string('insurance_claim_number', 60)->nullable();
            $table->bigInteger('estimated_damage_minor')->nullable();
            $table->json('photo_file_ids')->nullable();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders');
            $table->foreignId('reported_by')->constrained('users');
            $table->string('status', 20);

            $table->index(['school_id', 'vehicle_id'], 'vehicle_incidents_vehicle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_incidents');
    }
};
