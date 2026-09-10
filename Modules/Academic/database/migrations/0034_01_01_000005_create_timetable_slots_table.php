<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2 — one lesson in one slot. `cycle_day`/
 * `period_number` are denormalised from `period_slot_id` for fast
 * clash-detection queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('period_slot_id')->constrained();
            $table->tinyInteger('cycle_day');
            $table->smallInteger('period_number');
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('class_id')->nullable()->constrained('school_classes');
            $table->foreignId('teaching_group_id')->nullable()->constrained();
            $table->foreignId('staff_id')->constrained('staff');
            $table->foreignId('co_staff_id')->nullable()->constrained('staff');
            $table->foreignId('venue_id')->nullable()->constrained();
            $table->boolean('is_double')->default(false);
            $table->unsignedBigInteger('double_partner_slot_id')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->string('notes', 255)->nullable();

            $table->index(['school_id', 'timetable_id', 'cycle_day', 'period_number'], 'timetable_slots_slot_idx');
            $table->index(['school_id', 'timetable_id', 'staff_id', 'cycle_day'], 'timetable_slots_staff_idx');
            $table->index(['school_id', 'timetable_id', 'venue_id', 'cycle_day'], 'timetable_slots_venue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
