<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2 ⭐/BR-OPS-01-006. `fee_component_id` is a real FK
 * into `FIN-02`'s `fee_components` — the actual `USAGE_BASED` billing
 * computation itself is a documented, pre-existing Book B deferral
 * (`Modules\Finance\Domain\Exceptions\UnsupportedBillingBasisException`),
 * not something this module invents; the FK is real so that wiring
 * has something to attach to once it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_zones', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->decimal('max_distance_km', 6, 2)->nullable();
            $table->foreignId('fee_component_id')->nullable()->constrained('fee_components');
            $table->bigInteger('termly_fee_minor');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code'], 'transport_zones_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_zones');
    }
};
