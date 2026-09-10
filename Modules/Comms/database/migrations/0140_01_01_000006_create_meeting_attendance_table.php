<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-07 §2 ⭐/BR-COM-07-007/008. Raw join/leave, before any
 * scoring or matching — never itself an `ACA-04` attendance record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('scheduled_meetings')->cascadeOnDelete();
            $table->string('participant_identifier', 150);
            $table->foreignId('student_id')->nullable()->constrained();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('match_confidence', 20)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'meeting_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendance');
    }
};
