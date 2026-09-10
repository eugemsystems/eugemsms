<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2. The master staff record. `staff_qualifications`
 * and `staff_documents` are deferred to a later pass (see the
 * module's own scope note) — this table carries everything a first
 * pass of contracts, establishment, teacher allocation, workload, and
 * leave actually needs.
 *
 * Every encrypted-at-rest column the spec sizes as a narrow `VARCHAR`
 * (`national_registration_no VARCHAR(30)`, etc.) is widened to `TEXT`
 * here — Laravel's `encrypted` cast stores base64 ciphertext plus an
 * authentication tag and IV, which runs well past 30-40 characters
 * even for a short plaintext. The literal spec width would truncate
 * silently on save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('staff_number', 40);

            $table->string('title', 20)->nullable();
            $table->string('first_name', 80);
            $table->string('middle_names', 150)->nullable();
            $table->string('last_name', 80);
            $table->string('preferred_name', 80)->nullable();
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->char('nationality', 2)->default('ZW');
            $table->text('national_registration_no')->nullable();
            $table->text('passport_no')->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->foreignId('photo_file_id')->nullable()->constrained('files')->nullOnDelete();

            $table->string('primary_phone', 30);
            $table->string('alternate_phone', 30)->nullable();
            $table->string('personal_email', 150)->nullable();
            $table->string('work_email', 150)->nullable();
            $table->string('address_line_1', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 60)->nullable();

            $table->string('kin_name', 150)->nullable();
            $table->string('kin_relationship', 40)->nullable();
            $table->string('kin_phone', 30)->nullable();
            $table->string('kin_address', 255)->nullable();

            $table->string('staff_category', 30);
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('establishment_posts')->nullOnDelete();
            $table->foreignId('reports_to_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('joined_on');
            $table->date('confirmed_on')->nullable();
            $table->date('exited_on')->nullable();
            $table->string('exit_reason', 60)->nullable();
            $table->string('status', 20);

            $table->text('zimra_bp_number')->nullable();
            $table->text('nssa_number')->nullable();
            $table->string('nec_membership_number', 30)->nullable();
            $table->string('pension_scheme', 60)->nullable();
            $table->string('medical_aid_provider', 80)->nullable();
            $table->text('medical_aid_number')->nullable();

            $table->string('bank_name', 80)->nullable();
            $table->string('bank_branch', 80)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->char('bank_account_currency', 3)->nullable();

            $table->boolean('is_teaching')->default(false);
            $table->string('teacher_registration_no', 40)->nullable();
            $table->smallInteger('max_weekly_periods')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'staff_number']);
            $table->index(['school_id', 'status', 'staff_category']);
            $table->index(['school_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
