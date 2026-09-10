<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-04 §2 🇿🇼. `was_scheduled` follows the spec's own
 * `TIMESTAMP(1)` literally — a single-column boolean-ish flag naming
 * choice from the spec, not a typo here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('load_shedding_schedule', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('schedule_date');
            $table->string('stage', 20)->nullable();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamp('actual_outage_start')->nullable();
            $table->timestamp('actual_outage_end')->nullable();
            $table->boolean('was_scheduled')->nullable();
            $table->string('source', 30);
            $table->string('impact_note', 255)->nullable();

            $table->index(['school_id', 'schedule_date'], 'load_shedding_schedule_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('load_shedding_schedule');
    }
};
