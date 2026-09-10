<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/BR-BRD-06-012 — `guardian_consent_id` is mandatory
 * in practice (`CreatePrescriptionAction` requires it), nullable only
 * because a prescription record can, per the spec's own schema, exist
 * fractionally ahead of consent capture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('medication_name', 150);
            $table->string('dose', 60);
            $table->string('frequency', 60);
            $table->string('route', 30);
            $table->string('prescribed_by', 150);
            $table->date('prescribed_on');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_prn')->default(false);
            $table->tinyInteger('max_doses_per_day')->nullable();
            $table->unsignedBigInteger('prescription_file_id')->nullable();
            $table->foreignId('guardian_consent_id')->nullable()->constrained('medical_consents');
            $table->boolean('is_self_administered')->default(false);
            $table->string('storage_location', 150)->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
