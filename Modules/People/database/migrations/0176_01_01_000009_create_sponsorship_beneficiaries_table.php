<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 §3. A learner funded by a sponsorship, optionally with a performance condition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_beneficiaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsorship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('fee_liability_id')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20);
            $table->string('performance_condition', 255)->nullable();
            $table->boolean('condition_met')->nullable();
            $table->unique(['sponsorship_id', 'student_id'], 'sponsorship_beneficiaries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_beneficiaries');
    }
};
