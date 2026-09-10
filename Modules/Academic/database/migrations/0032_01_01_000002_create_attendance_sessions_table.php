<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-04 §2. `timetable_slot_id` has no FK constraint yet —
 * `ACA-03` (timetabling) is not built in this pass, same pattern as
 * `learner_fee_assignments.invoice_id`. `BelongsToSession`/`PeriodGuard`
 * are deliberately NOT applied here yet, matching every other
 * academic/financial model in the codebase so far — see the spawned
 * follow-up task auditing that gap codebase-wide rather than wiring it
 * piecemeal for this module alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->date('session_date');
            $table->string('mode', 20);
            $table->foreignId('class_id')->nullable()->constrained('school_classes');
            $table->foreignId('teaching_group_id')->nullable()->constrained();
            $table->foreignId('subject_id')->nullable()->constrained();
            $table->smallInteger('period_number')->nullable();
            $table->unsignedBigInteger('timetable_slot_id')->nullable();
            $table->smallInteger('expected_count')->default(0);
            $table->smallInteger('present_count')->default(0);
            $table->smallInteger('absent_count')->default(0);
            $table->smallInteger('late_count')->default(0);
            $table->smallInteger('excused_count')->default(0);
            $table->string('status', 20);
            $table->foreignId('marked_by')->nullable()->constrained('users');
            $table->timestamp('marked_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('device_source', 20)->nullable();
            $table->timestamps();

            $table->unique(
                ['school_id', 'session_date', 'mode', 'class_id', 'teaching_group_id', 'period_number'],
                'attendance_sessions_unique'
            );
            $table->index(['school_id', 'term_id', 'session_date', 'status'], 'attendance_sessions_status_idx');
            $table->index(['school_id', 'class_id', 'session_date'], 'attendance_sessions_class_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
