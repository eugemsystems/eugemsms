<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-02 §2/§3 ⭐. Every applicant field mirrors its `students`
 * counterpart so `ConvertApplicationToStudentAction` can copy across
 * with zero re-keying. Narrower than the spec's literal SQL:
 * `enquiry_id` (enquiries/the CRM funnel are deferred), `offer_document_id`
 * (no document-generation pipeline), and `previous_results` beyond a
 * bare JSON snapshot (no `student_prior_results` table to copy into)
 * are all left out or simplified — see the module's own scope note.
 * `application_number` is nullable, not the spec's literal NOT NULL:
 * BR-PPL-02-002 says it's "allocated on submission, not on draft
 * creation" — a `draft` row genuinely has none yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intake_id')->constrained();
            $table->string('application_number', 40)->nullable();

            $table->string('first_name', 80);
            $table->string('middle_names', 150)->nullable();
            $table->string('last_name', 80);
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->char('nationality', 2)->default('ZW');
            $table->text('national_registration_no')->nullable();
            $table->char('national_registration_no_hash', 64)->nullable();
            $table->text('birth_certificate_no')->nullable();
            $table->char('birth_certificate_no_hash', 64)->nullable();
            $table->string('home_language', 40)->nullable();
            $table->string('religion', 60)->nullable();
            $table->string('address_line_1', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 60)->nullable();

            $table->foreignId('requested_grade_level_id')->constrained('grade_levels');
            $table->string('requested_enrolment_type', 20);
            $table->string('requested_residency', 20);
            $table->string('requested_pathway', 20)->nullable();
            $table->json('requested_subjects')->nullable();

            $table->string('previous_school', 200)->nullable();
            $table->string('previous_grade', 30)->nullable();
            $table->json('previous_results')->nullable();

            $table->boolean('has_sibling_at_school')->default(false);
            $table->foreignId('sibling_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->boolean('guardian_is_alumnus')->default(false);
            $table->boolean('guardian_is_staff')->default(false);
            $table->decimal('priority_score', 6, 2)->nullable();

            $table->string('status', 30);
            $table->foreignId('application_fee_receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->foreignId('deposit_receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->timestamp('offer_made_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('declined_reason', 120)->nullable();
            $table->smallInteger('waitlist_position')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'application_number']);
            $table->index(['school_id', 'intake_id', 'status']);
            $table->index(['school_id', 'status', 'priority_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
