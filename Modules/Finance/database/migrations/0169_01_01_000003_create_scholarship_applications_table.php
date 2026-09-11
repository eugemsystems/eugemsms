<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K FIN-07 §2/BR-FIN-07-005/006.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_applications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('scheme_id')->constrained('discount_schemes');
            $table->foreignId('student_id')->constrained();
            $table->foreignId('applied_by_guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->string('household_income_band', 30)->nullable();
            $table->json('supporting_document_ids')->nullable();
            $table->decimal('means_assessment_score', 5, 2)->nullable();
            $table->decimal('academic_average_at_application', 5, 2)->nullable();
            $table->text('narrative')->nullable();
            $table->string('status', 20);
            $table->text('committee_notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'scheme_id', 'status'], 'scholarship_applications_school_year_scheme_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_applications');
    }
};
