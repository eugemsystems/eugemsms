<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/BR-BRD-06-017 ⭐ — closes the `hospital` roll-status
 * stub `BRD-02` §4 left open. `transport_request_id` (OPS-01) and
 * `ad_hoc_charge_id` (FIN-02) are forward references with no owning
 * table yet, kept as plain nullable columns, matching the same
 * deferral pattern used throughout this codebase for genuinely
 * not-yet-built modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_referrals', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('admission_id')->nullable()->constrained('sick_bay_admissions');
            $table->unsignedBigInteger('incident_id')->nullable();
            $table->string('referral_type', 30);
            $table->string('facility_name', 200);
            $table->text('reason');
            $table->string('urgency', 20);
            $table->timestamp('referred_at');
            $table->foreignId('referred_by')->constrained('users');
            $table->string('transport_method', 30)->nullable();
            $table->unsignedBigInteger('transport_request_id')->nullable();
            $table->foreignId('escort_staff_id')->nullable()->constrained('staff');
            $table->timestamp('guardian_notified_at')->nullable();
            $table->boolean('guardian_present')->nullable();
            $table->string('consent_reference', 80)->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('outcome')->nullable();
            $table->bigInteger('cost_minor')->nullable();
            $table->string('cost_borne_by', 30)->nullable();
            $table->unsignedBigInteger('ad_hoc_charge_id')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'referred_at']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_referrals');
    }
};
