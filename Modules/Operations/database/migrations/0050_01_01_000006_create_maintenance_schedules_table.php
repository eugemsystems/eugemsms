<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-02 §2/BR-OPS-02-009/010. `last_generated_wo_id` is a
 * forward reference to `work_orders` within this same migration set —
 * plain nullable column, no FK, since the generated row doesn't exist
 * until the schedule fires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_asset_id')->constrained('maintenance_assets');
            $table->string('name', 150);
            $table->string('trigger_type', 20);
            $table->smallInteger('interval_days')->nullable();
            $table->decimal('interval_units', 12, 2)->nullable();
            $table->smallInteger('lead_time_days')->default(7);
            $table->json('task_checklist');
            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->json('estimated_parts')->nullable();
            $table->string('assigned_team', 20);
            $table->date('next_due_on')->nullable();
            $table->decimal('next_due_units', 12, 2)->nullable();
            $table->unsignedBigInteger('last_generated_wo_id')->nullable();
            $table->boolean('is_active')->default(true);

            $table->index(['school_id', 'next_due_on', 'is_active'], 'maint_schedules_due_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
