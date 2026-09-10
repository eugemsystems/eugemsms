<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2 — clinical (Tier 3); `presenting_complaint`,
 * `assessment` and `plan` are `SecondaryEncrypted`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('admission_id')->nullable()->constrained('sick_bay_admissions');
            $table->timestamp('consulted_at');
            $table->string('consultation_type', 30);
            $table->text('presenting_complaint');
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->string('practitioner_type', 30);
            $table->foreignId('practitioner_staff_id')->nullable()->constrained('staff');
            $table->string('external_practitioner', 150)->nullable();
            $table->date('follow_up_on')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'consulted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
