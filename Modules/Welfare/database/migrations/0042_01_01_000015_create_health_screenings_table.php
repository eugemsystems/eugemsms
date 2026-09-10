<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2 — `results` is `SecondaryEncrypted` (cast applied
 * at the model as a JSON-then-encrypted string, per the model's own
 * cast order). `referral_id` is a plain nullable column, matching
 * `health_incidents.referral_id`'s reasoning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_screenings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->string('screening_type', 40);
            $table->date('screened_on');
            $table->text('results')->nullable();
            $table->string('outcome', 30);
            $table->unsignedBigInteger('referral_id')->nullable();
            $table->timestamp('guardian_informed_at')->nullable();
            $table->string('screened_by', 150);
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_screenings');
    }
};
