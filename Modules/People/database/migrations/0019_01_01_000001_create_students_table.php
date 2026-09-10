<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-01 §2. `national_registration_no`/`birth_certificate_no`/
 * `passport_no` are `text` (not the spec's literal VARCHAR length) —
 * Laravel's `encrypted` cast produces ciphertext well over a short
 * VARCHAR's capacity. Each carries a separate deterministic hash
 * column for duplicate detection without decryption (BR-PPL-01-008),
 * not shown in the spec's SQL but required by that same rule's prose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admission_number', 40);
            $table->string('former_admission_number', 40)->nullable();

            $table->string('first_name', 80);
            $table->string('middle_names', 150)->nullable();
            $table->string('last_name', 80);
            $table->string('preferred_name', 80)->nullable();
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->char('nationality', 2)->default('ZW');
            $table->string('home_language', 40)->nullable();
            $table->string('religion', 60)->nullable();
            $table->text('national_registration_no')->nullable();
            $table->char('national_registration_no_hash', 64)->nullable();
            $table->text('birth_certificate_no')->nullable();
            $table->char('birth_certificate_no_hash', 64)->nullable();
            $table->text('passport_no')->nullable();
            $table->foreignId('photo_file_id')->nullable()->constrained('files')->nullOnDelete();

            // ⭐ billing attribute contract — all NOT NULL, all event-emitting
            $table->string('enrolment_type', 20);
            $table->string('residency', 20);
            $table->foreignId('section_id')->constrained('school_sections');
            $table->foreignId('grade_level_id')->constrained('grade_levels');
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('house_id')->nullable()->constrained('houses')->nullOnDelete();
            $table->string('pathway', 20)->nullable();
            $table->smallInteger('entry_cohort_year');

            $table->string('status', 20);
            $table->string('status_reason_code', 40)->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('enrolled_on')->nullable();
            $table->date('exited_on')->nullable();

            $table->string('address_line_1', 200)->nullable();
            $table->string('address_line_2', 200)->nullable();
            $table->string('suburb', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 60)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('transport_zone_id')->nullable();

            $table->boolean('has_medical_alert')->default(false);
            $table->boolean('has_allergy_alert')->default(false);
            $table->boolean('has_dietary_requirement')->default(false);
            $table->boolean('has_sen_record')->default(false);
            $table->boolean('has_safeguarding_flag')->default(false);
            $table->boolean('is_vulnerable')->default(false);
            $table->string('blood_group', 5)->nullable();

            $table->string('rfid_tag', 60)->nullable();
            $table->string('biometric_reference', 120)->nullable();
            $table->timestamp('id_card_issued_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'admission_number']);
            $table->unique(['school_id', 'rfid_tag']);
            $table->index(['school_id', 'status', 'grade_level_id']);
            $table->index(['school_id', 'class_id', 'status']);
            $table->index(['school_id', 'enrolment_type', 'residency'], 'students_school_billing_idx');
            $table->index(['school_id', 'last_name', 'first_name']);
            $table->index(['school_id', 'house_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
