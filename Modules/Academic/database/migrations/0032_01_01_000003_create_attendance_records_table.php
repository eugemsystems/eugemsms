<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-04 §2/§3/BR-ACA-04-008. Two columns beyond the spec's own
 * literal schema, both documented spec-silence fills:
 *
 * - `idempotency_key`: §3's own rule table states marks are "idempotent
 *   on (session_id, student_id, idempotency_key)" but the §2 data model
 *   never adds the column that sentence requires. Nullable — a mark
 *   made outside the offline-sync path (a teacher on the web UI) has
 *   none.
 * - `original_status`: BR-ACA-04-008 requires "the original value
 *   remains visible" after an amendment, but no column preserves it in
 *   the spec's schema (`amended_by`/`amended_at`/`amendment_reason`
 *   record the *amendment*, not the *original*). Set once, on the
 *   first amendment only, from whatever `status` held immediately
 *   before it — never overwritten again, mirroring how other
 *   append-only-ish audit columns in this codebase are guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->date('session_date');
            $table->string('status', 20);
            $table->string('original_status', 20)->nullable();
            $table->foreignId('reason_code_id')->nullable()->constrained('attendance_reason_codes');
            $table->smallInteger('minutes_late')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('idempotency_key', 80)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users');
            $table->timestamp('marked_at');
            $table->foreignId('amended_by')->nullable()->constrained('users');
            $table->timestamp('amended_at')->nullable();
            $table->string('amendment_reason', 255)->nullable();
            $table->timestamp('guardian_notified_at')->nullable();
            $table->unsignedBigInteger('notification_id')->nullable();

            $table->unique(['session_id', 'student_id'], 'attendance_records_unique');
            $table->index(['school_id', 'student_id', 'session_date'], 'attendance_records_student_date_idx');
            $table->index(['school_id', 'term_id', 'student_id', 'status'], 'attendance_records_term_status_idx');
            $table->index(['school_id', 'session_date', 'status'], 'attendance_records_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
