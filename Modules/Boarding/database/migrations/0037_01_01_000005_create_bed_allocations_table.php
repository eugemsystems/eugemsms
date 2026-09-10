<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2/§4/BR-BRD-01-003/004/006 ⭐ — dated occupancy
 * history. A bed holds at most one allocation on any date
 * (BR-BRD-01-003); mid-term movement supersedes rather than
 * overwrites (BR-BRD-01-006) — `AllocateBedAction`/`MoveLearnerAction`
 * enforce this via an `effective_to`/`status` transition, never a
 * row update of `bed_id`. `hostel_id`/`room_id` are intentionally
 * denormalised for fast hostel-wide rolls, per the spec's own
 * comment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bed_allocations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('bed_id')->constrained('hostel_beds');
            $table->foreignId('hostel_id')->constrained('hostels');
            $table->foreignId('room_id')->constrained('hostel_rooms');
            $table->string('allocation_type', 20);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20);
            $table->string('reason', 255)->nullable();
            $table->foreignId('allocated_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'bed_id', 'effective_from'], 'bed_allocations_bed_unique');
            $table->unique(['school_id', 'student_id', 'effective_from'], 'bed_allocations_student_unique');
            $table->index(['school_id', 'term_id', 'hostel_id', 'status'], 'bed_allocations_hostel_idx');
            $table->index(['school_id', 'student_id', 'effective_from'], 'bed_allocations_student_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_allocations');
    }
};
