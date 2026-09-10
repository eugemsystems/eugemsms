<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/BR-BRD-06-007 — deliberately Tier 2: every field
 * here is meant to be read by any staff member with care
 * responsibility, in plain language, with no diagnosis beyond what is
 * needed to act.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_care_plans', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('condition_id')->nullable()->constrained('medical_conditions');
            $table->string('title', 150);
            $table->text('trigger_signs');
            $table->text('immediate_actions');
            $table->string('medication_location', 150)->nullable();
            $table->string('medication_name', 120)->nullable();
            $table->text('do_not_do')->nullable();
            $table->string('who_to_call', 255);
            $table->date('review_due_on')->nullable();
            $table->foreignId('approved_by_nurse')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->boolean('guardian_acknowledged')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_care_plans');
    }
};
