<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-11 §2/BR-ACA-11-001. One scheme per (term, subject,
 * grade level, teacher) — the unique constraint below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schemes_of_work', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();
            $table->foreignId('teacher_staff_id')->constrained('staff');
            $table->json('planned_topics');
            $table->foreignId('document_file_id')->nullable()->constrained('files');
            $table->string('status', 20);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('review_comments')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'subject_id', 'grade_level_id', 'teacher_staff_id'], 'schemes_of_work_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schemes_of_work');
    }
};
