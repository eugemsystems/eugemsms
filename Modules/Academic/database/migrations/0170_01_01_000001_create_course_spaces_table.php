<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-08 §2/BR-ACA-08-001. One course space per teaching group
 * per term, mapped 1:1 from `ACA-02`'s `teaching_groups` — there is no
 * separate LMS-only class list, hence the unique constraint below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_spaces', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('teaching_group_id')->constrained();
            $table->foreignId('teacher_staff_id')->nullable()->constrained('staff');
            $table->foreignId('banner_image_file_id')->nullable()->constrained('files');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'teaching_group_id'], 'course_spaces_group_term_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_spaces');
    }
};
