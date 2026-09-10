<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-003/006. `safeguarding_concern_id` is
 * one column beyond the spec's literal schema — see
 * `Modules\Comms\Domain\Actions\RaiseComplaintAction`'s own docblock
 * for why a COMPLAINT links to a `Modules\Welfare\Models\SafeguardingConcern`
 * (the low-friction "reported" tier BRD-08 itself opens first, per
 * `ReportSafeguardingConcernAction`'s own docblock), not directly to
 * a `SafeguardingCase` (the higher tier BRD-08 staff open only after
 * their own triage — not this module's decision to make).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('complaint_number', 40);
            $table->foreignId('category_id')->constrained('complaint_categories');
            $table->string('raised_by_type', 20);
            $table->unsignedBigInteger('raised_by_id')->nullable();
            $table->string('subject', 200);
            $table->text('description');
            $table->foreignId('related_student_id')->nullable()->constrained('students');
            $table->string('severity', 20);
            $table->foreignId('assigned_to_staff_id')->nullable()->constrained('staff');
            $table->timestamp('sla_due_at');
            $table->string('status', 20);
            $table->text('resolution')->nullable();
            $table->tinyInteger('satisfaction_rating')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('safeguarding_concern_id')->nullable()->constrained('safeguarding_concerns');
            $table->timestamps();

            $table->unique(['school_id', 'complaint_number']);
            $table->index(['school_id', 'status', 'sla_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
