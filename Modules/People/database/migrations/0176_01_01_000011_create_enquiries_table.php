<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-02 §2. The top of the admissions funnel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intake_id')->nullable()->constrained('intakes');
            $table->string('source', 30);
            $table->string('enquirer_name', 150);
            $table->string('enquirer_phone', 30)->nullable();
            $table->string('enquirer_email', 150)->nullable();
            $table->string('learner_name', 150)->nullable();
            $table->date('learner_dob')->nullable();
            $table->foreignId('interested_grade_level_id')->nullable()->constrained('grade_levels');
            $table->string('interested_residency', 20)->nullable();
            $table->text('message')->nullable();
            $table->string('stage', 30);
            $table->string('lost_reason', 60)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->date('next_follow_up_on')->nullable();
            $table->foreignId('application_id')->nullable()->constrained('applications');
            $table->timestamps();
            $table->index(['school_id', 'stage', 'next_follow_up_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
