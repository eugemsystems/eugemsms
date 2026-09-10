<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §2/BR-ACA-05-006/007/008/009 ⭐. The current value —
 * `assessment_mark_versions` (next migration) is the append-only
 * history behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->decimal('raw_mark', 6, 2)->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->decimal('points', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('absence_reason', 60)->nullable();
            $table->string('comment', 255)->nullable();
            $table->smallInteger('version')->default(1);
            $table->foreignId('entered_by')->constrained('users');
            $table->timestamp('entered_at');

            $table->unique(['assessment_id', 'student_id'], 'assessment_marks_unique');
            $table->index(['school_id', 'student_id', 'term_id'], 'assessment_marks_student_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_marks');
    }
};
