<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('routes');
            $table->smallInteger('sequence');
            $table->string('name', 150);
            $table->string('landmark', 200)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distance_from_school_km', 6, 2)->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('transport_zones');
            $table->time('scheduled_time')->nullable();

            $table->unique(['route_id', 'sequence'], 'route_stops_route_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};
