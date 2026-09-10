<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2/§3. `proximity_to_exit`/`is_ground_floor` are the
 * medical/mobility placement constraints the allocation engine
 * honours without ever seeing the diagnosis behind them (§3's
 * flag-versus-detail split).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_rooms', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained('hostels');
            $table->foreignId('wing_id')->nullable()->constrained('hostel_wings');
            $table->string('room_number', 20);
            $table->string('room_type', 20);
            $table->smallInteger('bed_count');
            $table->string('condition_grade', 20)->default('good');
            $table->boolean('is_ground_floor')->default(false);
            $table->string('proximity_to_exit', 20)->nullable();
            $table->string('proximity_to_ablution', 20)->nullable();
            $table->boolean('has_power_outlet')->default(true);
            $table->string('notes', 255)->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'hostel_id', 'room_number'], 'hostel_rooms_number_unique');
            $table->index(['school_id', 'hostel_id', 'condition_grade'], 'hostel_rooms_condition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_rooms');
    }
};
