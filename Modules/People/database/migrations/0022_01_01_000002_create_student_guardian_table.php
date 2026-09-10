<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 §3 ⭐/BR-PPL-03-005. The relationship, and its rights —
 * each granted independently, never inferred from `relationship`.
 * Narrower than the spec's literal SQL: `may_authorise_exeat`,
 * `may_authorise_medical`, `may_receive_results`, `may_view_discipline`,
 * `has_portal_access`, `restriction_note`/`restriction_document_id`,
 * `lives_with_learner`, and `contact_priority` belong to screens
 * (portal provisioning, the gate terminal, discipline visibility) this
 * pass does not build. Kept: the rights `FIN-03`'s liability
 * resolution and its own tests actually exercise —
 * `is_fee_responsible`, `may_view_full_balance` — plus the identity/
 * contact/restriction rights named directly in Book C's business rules
 * (`is_primary_contact`, `is_emergency_contact`, `has_court_restriction`,
 * `may_collect_learner`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_guardian', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 30);
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('is_fee_responsible')->default(false);
            $table->boolean('may_collect_learner')->default(false);
            $table->boolean('may_view_full_balance')->default(false);
            $table->boolean('has_court_restriction')->default(false);
            $table->string('status', 20)->default('active');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'guardian_id']);
            $table->index(['school_id', 'student_id', 'is_fee_responsible']);
            $table->index(['school_id', 'guardian_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardian');
    }
};
