<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2/§6/BR-ACA-03-016/017/018/019 — cover.
 * `leave_request_id` has no FK constraint — `PPL-04`'s leave table is
 * referenced but this module doesn't otherwise depend on its schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_substitutions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('timetable_slot_id')->constrained();
            $table->date('substitution_date');
            $table->foreignId('absent_staff_id')->constrained('staff');
            $table->foreignId('cover_staff_id')->nullable()->constrained('staff');
            $table->string('reason', 60);
            $table->unsignedBigInteger('leave_request_id')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained();
            $table->string('status', 20);
            $table->text('work_set')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users');

            $table->unique(['school_id', 'timetable_slot_id', 'substitution_date'], 'lesson_substitutions_unique');
            $table->index(['school_id', 'substitution_date', 'status'], 'lesson_substitutions_date_idx');
            $table->index(['school_id', 'cover_staff_id', 'substitution_date'], 'lesson_substitutions_cover_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_substitutions');
    }
};
