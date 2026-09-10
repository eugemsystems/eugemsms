<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('water_source_id')->constrained('water_sources');
            $table->date('read_on');
            $table->decimal('storage_level_percent', 5, 2)->nullable();
            $table->decimal('volume_pumped_litres', 12, 2)->nullable();
            $table->decimal('pump_hours', 6, 2)->nullable();
            $table->decimal('yield_observed', 10, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('read_by')->constrained('users');

            $table->unique(['water_source_id', 'read_on'], 'water_readings_source_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_readings');
    }
};
