<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-03 §2 — APPEND-ONLY, mirroring `movement_log`'s own
 * reasoning (a permanent visit record, never edited after the fact).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_logs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained('visitors');
            $table->string('visit_purpose', 40);
            $table->foreignId('host_staff_id')->nullable()->constrained('staff');
            $table->foreignId('student_id')->nullable()->constrained();
            $table->string('vehicle_registration', 30)->nullable();
            $table->string('badge_number', 30)->nullable();
            $table->timestamp('signed_in_at');
            $table->timestamp('signed_out_at')->nullable();
            $table->smallInteger('expected_duration_mins')->nullable();
            $table->foreignId('gate_staff_in')->constrained('users');
            $table->foreignId('gate_staff_out')->nullable()->constrained('users');
            $table->string('items_declared', 255)->nullable();
            $table->boolean('induction_completed')->default(false);
            $table->text('notes')->nullable();

            $table->index(['school_id', 'signed_in_at'], 'visitor_logs_signed_in_idx');
            $table->index(['school_id', 'student_id', 'signed_in_at'], 'visitor_logs_student_idx');
            $table->index(['school_id', 'signed_out_at'], 'visitor_logs_signed_out_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
    }
};
