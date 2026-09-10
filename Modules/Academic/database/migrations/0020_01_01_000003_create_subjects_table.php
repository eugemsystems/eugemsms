<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-01 §2. Deliberately narrower than the spec's literal SQL —
 * `cambridge_subject_code`, `has_practical_component`, `has_coursework`,
 * `coursework_weight_percent`, `default_periods_per_week`,
 * `department_id` and `grading_scale_id` belong to timetabling and
 * assessment (`ACA-03`/`ACA-05`), which this pass does not build. Only
 * the columns the subject-count contract and the selection rule engine
 * need are kept: identity, framework/group linkage, and `requires_sbp`
 * (BR-ACA-01-015, referenced by name in the seeded rule set).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('framework_id')->constrained('curriculum_frameworks');
            $table->foreignId('subject_group_id')->nullable()->constrained('subject_groups')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('short_name', 40);
            $table->string('zimsec_subject_code', 20)->nullable();
            $table->string('subject_type', 20);
            $table->boolean('is_examinable')->default(true);
            $table->boolean('requires_sbp')->default(true);
            $table->smallInteger('sort_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'framework_id', 'code']);
            $table->index(['school_id', 'subject_group_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
