<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §3 ⭐. Derived cache, per term — recalculated by
 * `RecalculateStaffWorkloadAction`, never written to directly
 * elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_workload', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->smallInteger('teaching_periods')->default(0);
            $table->tinyInteger('class_teacher_count')->default(0);
            $table->smallInteger('duty_count')->default(0);
            $table->tinyInteger('subject_count')->default(0);
            $table->tinyInteger('class_count')->default(0);
            $table->smallInteger('learner_count')->default(0);
            $table->decimal('utilisation_percent', 5, 2)->nullable();
            $table->boolean('is_overloaded')->default(false);
            $table->timestamp('recalculated_at');

            $table->unique(['school_id', 'staff_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_workload');
    }
};
