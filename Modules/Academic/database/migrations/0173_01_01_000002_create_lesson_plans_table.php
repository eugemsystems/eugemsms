<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-11 §2/BR-ACA-11-001/003.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_of_work_id')->nullable()->constrained('schemes_of_work');
            $table->foreignId('timetable_slot_id')->nullable()->constrained('timetable_slots');
            $table->foreignId('teacher_staff_id')->constrained('staff');
            $table->date('lesson_date');
            $table->string('topic', 200);
            $table->text('objectives')->nullable();
            $table->text('activities')->nullable();
            $table->string('resources_needed', 500)->nullable();
            $table->text('differentiation_notes')->nullable();
            $table->string('status', 20);
            $table->text('hod_comments')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'teacher_staff_id', 'lesson_date'], 'lesson_plans_teacher_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
