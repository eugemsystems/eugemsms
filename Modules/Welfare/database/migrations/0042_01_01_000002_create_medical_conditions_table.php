<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/§0.2 ⭐ — the three-tier visibility model lives on
 * this one table: `public_summary`/`affects_*`/`accommodation_requirement`
 * are Tier 2, `diagnosis_notes`/`diagnosed_by`/`supporting_document_id`
 * are Tier 3. Tier separation is enforced in the Action layer that
 * reads this table (`ResolveMedicalTierAction` decides what a caller
 * gets back), never by hiding columns client-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_conditions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('condition_type', 30);
            $table->string('category', 40)->nullable();
            $table->text('name');
            $table->string('severity', 20);
            $table->string('public_summary', 255)->nullable();
            $table->boolean('requires_emergency_plan')->default(false);
            $table->boolean('affects_dietary')->default(false);
            $table->boolean('affects_physical_activity')->default(false);
            $table->boolean('affects_accommodation')->default(false);
            $table->string('accommodation_requirement', 120)->nullable();
            $table->text('diagnosis_notes')->nullable();
            $table->date('diagnosed_on')->nullable();
            $table->string('diagnosed_by', 150)->nullable();
            $table->unsignedBigInteger('supporting_document_id')->nullable();
            $table->boolean('verified_by_nurse')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->string('status', 20);
            $table->string('declared_by', 30);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'status']);
            $table->index(['school_id', 'severity', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_conditions');
    }
};
