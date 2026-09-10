<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-02 §2 — one admissions cycle for one grade level.
 * `public_form_slug`/`requires_entrance_exam`/`requires_interview` are
 * kept for schema completeness even though the public form, entrance
 * exams, and interviews are not built in this pass — see
 * `AcademicServiceProvider`-style scope notes on `ConvertApplicationToStudentAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intakes', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('name', 150);
            $table->foreignId('grade_level_id')->constrained();
            $table->date('opens_on');
            $table->date('closes_on');
            $table->smallInteger('target_places');
            $table->smallInteger('places_offered')->default(0);
            $table->smallInteger('places_accepted')->default(0);
            $table->bigInteger('application_fee_minor')->nullable();
            $table->char('application_fee_currency', 3)->nullable();
            $table->bigInteger('acceptance_deposit_minor')->nullable();
            $table->char('acceptance_deposit_currency', 3)->nullable();
            $table->smallInteger('deposit_deadline_days')->default(14);
            $table->boolean('requires_entrance_exam')->default(false);
            $table->boolean('requires_interview')->default(false);
            $table->string('status', 20);
            $table->boolean('public_form_enabled')->default(true);
            $table->string('public_form_slug', 80)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intakes');
    }
};
