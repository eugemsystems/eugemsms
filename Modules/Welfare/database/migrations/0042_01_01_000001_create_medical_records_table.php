<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2 — one row per learner, holding non-clinical medical
 * administrivia (medical aid, family doctor, preferred hospital) plus
 * `notes`, which IS clinical free text and carries `SecondaryEncrypted`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('blood_group', 5)->nullable();
            $table->smallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->date('last_measured_on')->nullable();
            $table->string('medical_aid_provider', 80)->nullable();
            $table->text('medical_aid_number')->nullable();
            $table->string('medical_aid_principal', 150)->nullable();
            $table->string('family_doctor_name', 150)->nullable();
            $table->string('family_doctor_phone', 30)->nullable();
            $table->string('preferred_hospital', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->foreignId('last_reviewed_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
