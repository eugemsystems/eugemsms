<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/BR-BRD-06-010 ⭐ — APPEND-ONLY LEGAL RECORD. The
 * spec's real enforcement (revoking UPDATE/DELETE for the application
 * database user) is an environment-specific deployment step — see
 * `Modules\Core\Models\FinancialAuditLogEntry`'s identical, already-
 * documented deferral. The model-level `booted()` guard here is what
 * is actually testable and always in effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_administrations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('admission_id')->nullable()->constrained('sick_bay_admissions');
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions');
            $table->string('medication_name', 150);
            $table->string('dose', 60);
            $table->string('route', 30);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('administered_at');
            $table->foreignId('administered_by')->constrained('users');
            $table->foreignId('witnessed_by')->nullable()->constrained('users');
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('consent_reference', 80)->nullable();
            $table->string('outcome', 30)->nullable();
            $table->string('omission_reason', 255)->nullable();
            $table->text('adverse_reaction')->nullable();
            $table->string('notes', 255)->nullable();

            $table->index(['school_id', 'student_id', 'administered_at'], 'medication_admin_student_idx');
            $table->index(['school_id', 'admission_id'], 'medication_admin_admission_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
    }
};
