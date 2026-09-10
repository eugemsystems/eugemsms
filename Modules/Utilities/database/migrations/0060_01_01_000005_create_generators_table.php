<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2. `fixed_asset_id` (`FIN-10`) and
 * `maintenance_asset_id` (`OPS-02`) are real FKs — both modules
 * already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generators', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->decimal('capacity_kva', 8, 2);
            $table->string('fuel_type', 20)->default('diesel');
            $table->decimal('tank_capacity_litres', 8, 2)->nullable();
            $table->decimal('expected_litres_per_hour', 6, 3)->nullable();
            $table->string('serves_scope', 30);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->decimal('current_hours', 10, 2)->default(0);
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets');
            $table->foreignId('maintenance_asset_id')->nullable()->constrained('maintenance_assets');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->string('status', 20);

            $table->unique(['school_id', 'code'], 'generators_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generators');
    }
};
