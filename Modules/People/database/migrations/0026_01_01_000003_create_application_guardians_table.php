<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-02 §2/§3. Captured at application, converted with it —
 * `ConvertApplicationToStudentAction` matches each row to an existing
 * `Guardian` (by normalised `primary_phone` only in this pass — no
 * national-registration hash column exists on either `guardians` or
 * here, matching `guardians`' own already-recorded PII scope cut) or
 * creates a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 30);
            $table->string('title', 20)->nullable();
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('organisation_name', 200)->nullable();
            $table->string('primary_phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_fee_responsible')->default(false);
            $table->foreignId('existing_guardian_id')->nullable()->constrained('guardians')->nullOnDelete();

            $table->index('application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_guardians');
    }
};
