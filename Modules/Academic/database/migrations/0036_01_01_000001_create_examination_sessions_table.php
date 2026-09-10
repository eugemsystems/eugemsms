<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2. One examination series — 'End of Term 3 2026',
 * 'Form 4 Mocks'. `exam_slot_plan_id` reserves venue/staff time
 * through `ACA-03`'s own table of the same name (built for exactly
 * this purpose in that module's own pass).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('name', 150);
            $table->string('exam_type', 30);
            $table->string('exam_body', 30);
            $table->json('affected_levels');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('index_number_pattern', 80)->nullable();
            $table->foreignId('exam_slot_plan_id')->nullable()->constrained('exam_slot_plans');
            $table->string('status', 20);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'term_id', 'status'], 'exam_sessions_term_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_sessions');
    }
};
