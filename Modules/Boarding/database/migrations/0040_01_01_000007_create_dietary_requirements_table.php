<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-04 §2/BR-BRD-04-009/010/011 ⭐ — safeguarding, not
 * preference. `medical_source_id` is a forward reference to `BRD-06`
 * (Book G, not built yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dietary_requirements', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('requirement_type', 30);
            $table->string('severity', 20);
            $table->json('allergens')->nullable();
            $table->json('excluded_items')->nullable();
            $table->string('description', 255);
            $table->string('alternative_provision', 255)->nullable();
            $table->unsignedBigInteger('medical_source_id')->nullable();
            $table->boolean('requires_epipen')->default(false);
            $table->boolean('verified_by_nurse')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);

            $table->index(['school_id', 'is_active', 'severity'], 'dietary_requirements_severity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dietary_requirements');
    }
};
