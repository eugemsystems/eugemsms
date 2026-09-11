<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K FIN-07 §2 ⭐ — the granted instance; what `FIN-02`'s
 * `DiscountResolver` reads. `term_id` null = whole year.
 *
 * Named `discount_awards` here, not the spec's literal `awards` —
 * `Modules\Sport` (Book H2 OPS-07) already owns a table literally
 * named `awards` for colours/honours, a genuine naming collision the
 * spec itself doesn't flag since it was written without visibility
 * into OPS-07's own table name. `Modules\Finance\Models\DiscountAward`
 * is the model class, matching this table's name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_awards', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained('discount_schemes');
            $table->foreignId('student_id')->constrained();
            $table->foreignId('application_id')->nullable()->constrained('scholarship_applications')->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->json('applies_to_components')->nullable();
            $table->string('award_method', 20);
            $table->decimal('award_percent', 5, 2)->nullable();
            $table->bigInteger('award_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->foreignId('sponsor_guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20);
            $table->string('condition_note', 255)->nullable();
            $table->timestamp('condition_last_checked_at')->nullable();
            $table->boolean('condition_met')->nullable();
            // Nullable, unlike the spec's literal NOT NULL — an
            // automatic scheme's award (sibling, staff-child) is
            // system-computed by AwardDiscountResolver on every
            // billing run, with no human granting it; forcing a fake
            // "granted by" user would misrepresent the audit trail.
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->string('revoked_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'scheme_id', 'student_id', 'academic_year_id', 'term_id'], 'discount_awards_school_scheme_student_year_term_unique');
            $table->index(['school_id', 'student_id', 'status']);
            $table->index(['school_id', 'scheme_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_awards');
    }
};
