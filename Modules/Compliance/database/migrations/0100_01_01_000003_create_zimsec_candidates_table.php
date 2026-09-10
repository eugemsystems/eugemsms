<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-01 §2/BR-CMP-01-001. Bio-data columns are populated
 * once, by `DeriveZimsecCandidatesAction`, copied straight from
 * `Modules\People\Models\Student` — never re-keyed, never edited here.
 * `ad_hoc_charge_id` replaces the spec's literal `invoice_id`: what
 * `BillZimsecEntryFeeAction` actually raises through the real
 * `CreateAdHocChargeAction` (Book B FIN-02) is an `ad_hoc_charges` row,
 * not an invoice — nothing in this codebase turns an ad hoc charge
 * into an invoice yet (a pre-existing FIN-02/FIN-03 gap, not this
 * module's to close), so naming the column for what it actually
 * points at is the honest choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zimsec_candidates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('zimsec_registrations')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('candidate_number', 40)->nullable();

            $table->string('surname', 80);
            $table->string('forenames', 200);
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->text('national_registration_no')->nullable();
            $table->string('national_registration_no_hash', 64)->nullable();
            $table->text('birth_certificate_no')->nullable();
            $table->string('birth_certificate_no_hash', 64)->nullable();

            $table->json('subject_entries');
            $table->tinyInteger('subject_count');
            $table->boolean('is_repeat_candidate')->default(false);
            $table->string('previous_candidate_no', 40)->nullable();
            $table->json('special_arrangements')->nullable();

            $table->bigInteger('entry_fee_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('ad_hoc_charge_id')->nullable();
            $table->boolean('fee_paid')->default(false);

            $table->string('validation_status', 20)->default('pending');
            $table->json('validation_errors')->nullable();
            $table->unsignedBigInteger('statement_of_entry_id')->nullable();
            $table->boolean('statement_confirmed')->default(false);
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique(['registration_id', 'student_id'], 'zimsec_candidates_student_unique');
            $table->index(['school_id', 'validation_status'], 'zimsec_candidates_validation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zimsec_candidates');
    }
};
