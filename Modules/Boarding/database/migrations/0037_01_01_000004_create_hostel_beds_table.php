<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2/BR-BRD-01-002. `hostels.capacity` is the sum of
 * `is_available` beds across a hostel's rooms — recomputed, never
 * hand-entered. `asset_tag`/`mattress_asset_tag` are forward
 * references to `FIN-10` fixed assets (Book H), no FK yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_beds', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('hostel_rooms');
            $table->string('bed_number', 20);
            $table->string('bed_type', 20);
            $table->string('asset_tag', 40)->nullable();
            $table->string('mattress_asset_tag', 40)->nullable();
            $table->string('condition_grade', 20)->default('good');
            $table->boolean('is_available')->default(true);
            $table->string('out_of_service_reason', 255)->nullable();

            $table->unique(['school_id', 'room_id', 'bed_number'], 'hostel_beds_number_unique');
            $table->index(['school_id', 'is_available'], 'hostel_beds_available_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_beds');
    }
};
