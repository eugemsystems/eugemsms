<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2/BR-ACA-03-003 — named time slots within a cycle
 * day. `is_teachable = 0` (break, assembly, chapel, games) can never
 * carry a lesson.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('period_structures')->cascadeOnDelete();
            $table->tinyInteger('cycle_day');
            $table->smallInteger('period_number');
            $table->string('label', 40);
            $table->string('slot_type', 20);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->smallInteger('duration_minutes');
            $table->boolean('is_teachable')->default(true);
            $table->boolean('requires_attendance')->default(true);
            $table->smallInteger('sort_order')->nullable();

            $table->unique(['structure_id', 'cycle_day', 'period_number'], 'period_slots_unique');
            $table->index(['school_id', 'structure_id', 'cycle_day'], 'period_slots_structure_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_slots');
    }
};
