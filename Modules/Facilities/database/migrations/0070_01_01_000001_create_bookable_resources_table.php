<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-05 §2. `venue_id` (`ACA-03`) and `vehicle_id` (`OPS-01`)
 * are both real FKs — both modules already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_resources', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles');
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('resource_type', 30);
            $table->smallInteger('capacity')->nullable();
            $table->boolean('is_externally_hireable')->default(false);
            $table->bigInteger('hire_rate_minor')->nullable();
            $table->string('hire_rate_unit', 20)->nullable();
            $table->char('hire_currency', 3)->nullable();
            $table->bigInteger('deposit_minor')->nullable();
            $table->smallInteger('requires_setup_minutes')->default(0);
            $table->smallInteger('requires_cleaning_minutes')->default(0);
            $table->smallInteger('booking_lead_time_hours')->default(24);
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->foreignId('income_account_id')->nullable()->constrained('accounts');
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code'], 'bookable_resources_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_resources');
    }
};
