<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2/BR-OPS-04-015/016/017.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('source_type', 30);
            $table->decimal('depth_metres', 8, 2)->nullable();
            $table->decimal('yield_litres_per_hour', 10, 2)->nullable();
            $table->string('pump_capacity', 60)->nullable();
            $table->decimal('storage_capacity_litres', 12, 2)->nullable();
            $table->foreignId('maintenance_asset_id')->nullable()->constrained('maintenance_assets');
            $table->string('status', 20);
            $table->date('last_tested_on')->nullable();
            $table->string('water_quality_status', 20)->nullable();
            $table->date('last_quality_test_on')->nullable();

            $table->unique(['school_id', 'code'], 'water_sources_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_sources');
    }
};
