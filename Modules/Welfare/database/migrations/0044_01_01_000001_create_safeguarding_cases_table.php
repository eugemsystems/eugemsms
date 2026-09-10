<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-08 §2/§3 ⭐⭐ — the inverted access model's central
 * record. No deletion path exists in the code for this table (never
 * mind the schema) — see `SafeguardingCase`'s own model-level guard.
 * `summary`/`guardians_not_informed_reason`/`closure_summary` are
 * `SecondaryEncrypted`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safeguarding_cases', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('case_reference', 40);
            $table->foreignId('student_id')->constrained();
            $table->timestamp('opened_at');
            $table->foreignId('opened_by')->constrained('users');
            $table->foreignId('lead_staff_id')->constrained('staff');
            $table->string('category', 40);
            $table->string('risk_level', 20);
            $table->text('summary');
            $table->string('status', 20);
            $table->boolean('external_agency_involved')->default(false);
            $table->boolean('guardians_informed')->nullable();
            $table->text('guardians_not_informed_reason')->nullable();
            $table->date('next_review_on')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->text('closure_summary')->nullable();
            $table->date('retention_until')->nullable();

            $table->unique(['school_id', 'case_reference']);
            $table->index(['school_id', 'status', 'risk_level']);
            $table->index(['school_id', 'next_review_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safeguarding_cases');
    }
};
