<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-08 §2/BR-BRD-08-018. `support_plan` is `SecondaryEncrypted`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vulnerable_learner_register', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('vulnerability_type', 40);
            $table->timestamp('identified_at');
            $table->foreignId('identified_by')->constrained('users');
            $table->text('support_plan')->nullable();
            $table->foreignId('assigned_mentor_id')->nullable()->constrained('staff');
            $table->smallInteger('review_frequency_days')->default(30);
            $table->date('next_review_on')->nullable();
            $table->string('status', 20);

            $table->index(['school_id', 'status', 'next_review_on'], 'vulnerable_register_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vulnerable_learner_register');
    }
};
