<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2 ⭐/BR-OPS-04-009. `cost_centre_id` on a submeter is
 * the real per-department energy allocation — a school-wide meter
 * leaves it null since it has no single owning cost centre.
 * `scope_id` is a plain polymorphic-ish reference (building/hostel/
 * department/farm id, per `serves_scope`) — no single FK target.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meters', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('utility_account_id')->constrained('utility_accounts');
            $table->string('meter_number', 60);
            $table->string('meter_type', 30);
            $table->string('location', 150);
            $table->string('serves_scope', 30);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres');
            $table->string('unit', 20);
            $table->decimal('multiplier', 8, 4)->default(1);
            $table->decimal('current_reading', 14, 3)->nullable();
            $table->decimal('current_balance_units', 12, 3)->nullable();
            $table->decimal('low_balance_threshold', 12, 3)->nullable();
            $table->date('last_read_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'meter_number'], 'meters_school_number_unique');
            $table->index(['school_id', 'meter_type', 'is_active'], 'meters_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meters');
    }
};
