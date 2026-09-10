<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-02 §2/BR-ACA-02-007. Append-only. `proration_factor` is
 * computed and stored at change time from a snapshot of
 * `term_teaching_days` — never recomputed later, so a subsequent
 * correction to the term's calendar (a holiday added, an unplanned
 * closure) cannot silently change an already-issued charge
 * (AC-ACA-02-003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_enrolment_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->string('change_type', 20);
            $table->date('effective_from');
            $table->smallInteger('teaching_days_remaining')->nullable();
            $table->smallInteger('term_teaching_days')->nullable();
            $table->decimal('proration_factor', 8, 6)->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');
            $table->boolean('billing_event_dispatched')->default(false);
            $table->string('billing_event_result', 30)->nullable();
            $table->string('billing_reference', 80)->nullable();

            $table->index(['school_id', 'student_id', 'term_id']);
            $table->index(['school_id', 'billing_event_dispatched'], 'subj_enrolment_changes_school_billing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_enrolment_changes');
    }
};
