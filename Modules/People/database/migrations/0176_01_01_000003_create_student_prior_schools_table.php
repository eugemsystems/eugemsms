<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-01 §2. Where a learner was before this school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_prior_schools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('school_name', 200);
            $table->string('school_type', 30)->nullable();
            $table->char('country', 2)->default('ZW');
            $table->string('province', 60)->nullable();
            $table->date('attended_from')->nullable();
            $table->date('attended_to')->nullable();
            $table->string('last_grade_completed', 30)->nullable();
            $table->string('reason_for_leaving', 255)->nullable();
            $table->foreignId('transfer_letter_file_id')->nullable()->constrained('files');
            $table->boolean('had_outstanding_fees')->default(false);
            $table->text('notes')->nullable();
            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_prior_schools');
    }
};
