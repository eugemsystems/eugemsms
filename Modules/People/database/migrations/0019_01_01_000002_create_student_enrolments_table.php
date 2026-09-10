<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrolments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('section_id')->constrained('school_sections');
            $table->foreignId('grade_level_id')->constrained('grade_levels');
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('house_id')->nullable()->constrained('houses')->nullOnDelete();
            $table->string('enrolment_type', 20);
            $table->string('residency', 20);
            $table->string('pathway', 20)->nullable();
            $table->string('status', 20);
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->boolean('is_repeat')->default(false);
            $table->decimal('attendance_percent', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'term_id']);
            $table->index(['school_id', 'term_id', 'grade_level_id', 'status'], 'student_enrolments_term_grade_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrolments');
    }
};
