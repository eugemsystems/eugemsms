<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-02 §2 ⭐/BR-ACA-02-001. THE billing source of truth for a
 * learner's subject count — no other table or column stores it.
 * `fee_line_id`/`credit_note_id` are plain nullable references (no FK
 * constraint) because `FIN-02`'s `learner_fee_lines` and the credit
 * note table it will need don't exist yet — mirrors how Finance's own
 * `exchange_rate_id` FK on `journal_lines` was added in a later
 * migration once `exchange_rates` existed (Book B FIN-06). The
 * constraint can be added the same way once `FIN-02` ships.
 * `subject_group_id` is denormalised from `subjects` at enrolment time
 * (spec §2) so a later change to a subject's group never rewrites an
 * already-stored historical fee-rate join point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_subject_enrolments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('subject_group_id')->nullable()->constrained('subject_groups')->nullOnDelete();
            $table->string('enrolment_reason', 30);
            $table->string('status', 20);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->string('billing_status', 20)->default('pending');
            $table->unsignedBigInteger('fee_line_id')->nullable();
            $table->unsignedBigInteger('credit_note_id')->nullable();
            $table->foreignId('added_by')->constrained('users');
            $table->timestamp('added_at');
            $table->foreignId('dropped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dropped_at')->nullable();
            $table->string('drop_reason', 255)->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'student_id', 'subject_id', 'effective_from'], 'learner_subject_enrolments_unique_change');
            $table->index(['school_id', 'term_id', 'student_id', 'status'], 'learner_subject_enrolments_billing_count_idx');
            $table->index(['school_id', 'term_id', 'subject_id', 'status'], 'learner_subject_enrolments_class_list_idx');
            $table->index(['school_id', 'student_id', 'effective_from'], 'learner_subject_enrolments_student_dated_idx');
            $table->index(['school_id', 'billing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_subject_enrolments');
    }
};
