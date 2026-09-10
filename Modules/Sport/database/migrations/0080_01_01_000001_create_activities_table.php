<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-07 §2. `coach_staff_id` (`People`), `fee_component_id`
 * (`FIN-02`) and `venue_id` (`ACA-03`) are all real FKs — every one
 * of those modules already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('activity_type', 20);
            $table->string('season', 20)->nullable();
            $table->string('gender_scope', 10)->default('both');
            $table->smallInteger('min_grade_ordinal')->nullable();
            $table->smallInteger('max_grade_ordinal')->nullable();
            $table->foreignId('coach_staff_id')->nullable()->constrained('staff');
            $table->foreignId('fee_component_id')->nullable()->constrained('fee_components');
            $table->boolean('requires_medical_clearance')->default(false);
            $table->boolean('requires_guardian_consent')->default(true);
            $table->smallInteger('max_participants')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained('venues');
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code'], 'activities_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
