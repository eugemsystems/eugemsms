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
        Schema::create('solar_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->decimal('capacity_kwp', 8, 2);
            $table->decimal('battery_capacity_kwh', 8, 2)->nullable();
            $table->string('serves_scope', 30);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('commissioned_on')->nullable();
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets');
            $table->foreignId('maintenance_asset_id')->nullable()->constrained('maintenance_assets');
            $table->string('status', 20);

            $table->unique(['school_id', 'code'], 'solar_installations_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_installations');
    }
};
