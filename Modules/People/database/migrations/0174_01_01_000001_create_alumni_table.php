<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K PPL-06 §2/§3 ⭐/BR-PPL-06-001/002. `academic_summary_snapshot`
 * is frozen once, at graduation, from ACA-05's term results and
 * OPS-07's awards — read once, stored, never re-queried live. See
 * `CreateAlumniRecordAction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->unique()->constrained();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('admission_number', 40);
            $table->smallInteger('graduation_year');
            $table->foreignId('final_grade_level_id')->constrained('grade_levels');
            $table->foreignId('final_house_id')->nullable()->constrained('houses');
            $table->json('academic_summary_snapshot');
            $table->string('current_occupation', 200)->nullable();
            $table->string('current_employer', 200)->nullable();
            $table->string('further_education', 200)->nullable();
            $table->string('current_city', 100)->nullable();
            $table->char('current_country', 2)->nullable();
            $table->boolean('is_notable')->default(false);
            $table->json('contact_preferences')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['school_id', 'graduation_year'], 'alumni_graduation_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni');
    }
};
