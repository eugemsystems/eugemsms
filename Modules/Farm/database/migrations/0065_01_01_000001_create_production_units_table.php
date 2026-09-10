<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2 🇿🇼 ⭐/BR-OPS-03-001. Each production unit is its
 * own cost centre — a real `Modules\Finance` FK, not an internal
 * grouping label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_units', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('unit_type', 30);
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->foreignId('manager_staff_id')->nullable()->constrained('staff');
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->decimal('area_hectares', 10, 4)->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code'], 'production_units_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_units');
    }
};
