<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-01 BR-PPL-01-010's "permanent merge record" — append-only (enforced at the model
 * level, same pattern as `financial_audit_log`/`PeriodSnapshot`, see `.ai/rules/migrations.md`).
 * Snapshots the merged-away learner's own admission number at merge time (it is never reused or
 * reassigned — `students.admission_number` stays immutable on both rows, satisfying "preserves
 * both admission numbers in history" without needing to touch either row's own number).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_merges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('surviving_student_id')->constrained('students');
            $table->foreignId('merged_student_id')->constrained('students');
            $table->string('merged_student_admission_number', 50);
            $table->string('reason', 500)->nullable();
            $table->foreignId('merged_by')->constrained('users');
            $table->timestamp('merged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_merges');
    }
};
