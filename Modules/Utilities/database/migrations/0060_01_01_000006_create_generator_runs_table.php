<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2 ⭐/BR-OPS-04-011/012 — the real `FIN-10`/`OPS-02`/
 * `FIN-09` linkage. `store_requisition_id` is a genuine `FIN-09`
 * requisition for a school-tank diesel draw.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generator_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('generator_id')->constrained('generators');
            $table->date('run_date');
            $table->timestamp('started_at');
            $table->timestamp('stopped_at')->nullable();
            $table->decimal('hours_run', 6, 2)->nullable();
            $table->decimal('start_hour_meter', 10, 2);
            $table->decimal('end_hour_meter', 10, 2)->nullable();
            $table->string('reason', 30);
            $table->string('load_shedding_stage', 20)->nullable();
            $table->decimal('diesel_litres', 8, 2)->nullable();
            $table->bigInteger('diesel_cost_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('litres_per_hour', 6, 3)->nullable();
            $table->boolean('is_anomaly')->default(false);
            $table->foreignId('store_requisition_id')->nullable()->constrained('store_requisitions');
            $table->foreignId('operated_by')->nullable()->constrained('users');
            $table->foreignId('journal_id')->nullable()->constrained('journals');

            $table->index(['school_id', 'generator_id', 'run_date'], 'generator_runs_generator_date_idx');
            $table->index(['school_id', 'reason', 'run_date'], 'generator_runs_reason_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generator_runs');
    }
};
