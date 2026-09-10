<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/BR-BRD-06-015/017 ⭐ — closes the `sick_bay` and
 * (via `external_referrals`) `hospital` roll-status stubs `BRD-02`
 * §4 left open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sick_bay_admissions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->timestamp('admitted_at');
            $table->foreignId('admitted_by')->constrained('users');
            $table->string('presenting_complaint', 255);
            $table->json('initial_observations')->nullable();
            $table->string('bed_reference', 30)->nullable();
            $table->boolean('is_isolation')->default(false);
            $table->string('isolation_reason', 120)->nullable();
            $table->string('severity', 20);
            $table->timestamp('guardian_notified_at')->nullable();
            $table->foreignId('guardian_notified_by')->nullable()->constrained('users');
            $table->timestamp('expected_discharge_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->foreignId('discharged_by')->nullable()->constrained('users');
            $table->string('discharge_destination', 30)->nullable();
            $table->text('discharge_notes')->nullable();
            $table->boolean('excused_from_lessons')->default(true);
            $table->boolean('excused_from_activity')->default(true);
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'admitted_at']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sick_bay_admissions');
    }
};
