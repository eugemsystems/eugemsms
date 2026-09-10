<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-04 §2/BR-ACA-04-011. Cache, rebuilt nightly and at term
 * close from `attendance_records` — never itself a source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('scope', 20);
            $table->foreignId('subject_id')->nullable()->constrained();
            $table->smallInteger('sessions_expected')->default(0);
            $table->smallInteger('present_count')->default(0);
            $table->smallInteger('absent_authorised')->default(0);
            $table->smallInteger('absent_unauthorised')->default(0);
            $table->smallInteger('late_count')->default(0);
            $table->decimal('attendance_percent', 5, 2)->nullable();
            $table->smallInteger('consecutive_absent_max')->default(0);
            $table->boolean('is_chronic_absentee')->default(false);
            $table->timestamp('rebuilt_at')->nullable();

            $table->unique(['school_id', 'student_id', 'term_id', 'scope', 'subject_id'], 'attendance_summaries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
