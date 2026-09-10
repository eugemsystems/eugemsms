<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2/§4 ⭐/BR-ACA-03-004. Hard and soft rules the
 * generator obeys and manual edits are checked against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_constraints', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('constraint_type', 40);
            $table->string('severity', 10);
            $table->smallInteger('weight')->default(1);
            $table->foreignId('subject_id')->nullable()->constrained();
            $table->foreignId('staff_id')->nullable()->constrained('staff');
            $table->foreignId('class_id')->nullable()->constrained('school_classes');
            $table->foreignId('venue_id')->nullable()->constrained();
            $table->foreignId('grade_level_id')->nullable()->constrained();
            $table->json('cycle_days')->nullable();
            $table->json('period_numbers')->nullable();
            $table->smallInteger('value')->nullable();
            $table->string('reason', 255)->nullable();
            $table->boolean('is_active')->default(true);

            $table->index(['school_id', 'academic_year_id', 'constraint_type', 'is_active'], 'timetable_constraints_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_constraints');
    }
};
