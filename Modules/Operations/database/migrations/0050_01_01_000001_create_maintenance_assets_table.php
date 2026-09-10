<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-02 §2. Wider than `FIN-10`'s asset register — a
 * building or a borehole is maintainable without ever being
 * capitalised. `vehicle_id` is a forward reference to `OPS-01` (built
 * next in this book), plain nullable column, no FK, matching this
 * codebase's own established forward-reference pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_assets', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('asset_type', 30);
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('location', 150)->nullable();
            $table->string('building', 80)->nullable();
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->string('criticality', 20)->default('normal');
            $table->string('condition', 20)->default('good');
            $table->date('commissioned_on')->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->smallInteger('service_interval_days')->nullable();
            $table->decimal('service_interval_units', 12, 2)->nullable();
            $table->date('last_serviced_on')->nullable();
            $table->decimal('last_service_units', 12, 2)->nullable();
            $table->date('next_service_due_on')->nullable();
            $table->decimal('next_service_due_units', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'asset_type', 'is_active'], 'maint_assets_type_active_idx');
            $table->index(['school_id', 'next_service_due_on'], 'maint_assets_next_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_assets');
    }
};
