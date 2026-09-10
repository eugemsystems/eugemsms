<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2/BR-ACA-03-020 🇿🇼 — reserves venues/staff for a
 * public examination period and produces the disruption report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_slot_plans', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('name', 150);
            $table->string('exam_body', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('affected_levels');
            $table->json('venues_reserved')->nullable();
            $table->json('staff_reserved')->nullable();
            $table->json('disruption_report')->nullable();
            $table->string('status', 20);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_slot_plans');
    }
};
