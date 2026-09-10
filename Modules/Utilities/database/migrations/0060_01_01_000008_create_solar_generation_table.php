<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2/BR-OPS-04-014.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solar_generation', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installation_id')->constrained('solar_installations');
            $table->date('record_date');
            $table->decimal('kwh_generated', 10, 3);
            $table->decimal('kwh_consumed', 10, 3)->nullable();
            $table->decimal('battery_state_percent', 5, 2)->nullable();
            $table->decimal('grid_offset_kwh', 10, 3)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->string('reading_method', 20);

            $table->unique(['installation_id', 'record_date'], 'solar_generation_installation_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_generation');
    }
};
