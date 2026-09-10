<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2 ⭐ — consumed by ACA-02 and ACA-03. `class_id`
 * references `school_classes` (Book A CORE-02), not a Finance or
 * Academic table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_allocations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('class_id')->constrained('school_classes');
            $table->string('role', 20);
            $table->smallInteger('weekly_periods');
            $table->boolean('is_class_teacher')->default(false);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20);
            $table->foreignId('allocated_by')->constrained('users');

            $table->unique(['school_id', 'term_id', 'staff_id', 'subject_id', 'class_id'], 'teacher_allocations_unique_assignment');
            $table->index(['school_id', 'term_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_allocations');
    }
};
