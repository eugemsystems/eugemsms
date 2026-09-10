<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-06 §2/BR-ACA-06-002/008 — the task set to learners.
 * `brief_document_id` has no FK constraint yet — `CORE-10`'s document
 * store, same forward-reference pattern as `learner_fee_assignments.invoice_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_briefs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('instrument_id')->constrained('assessment_instruments');
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('grade_level_id')->constrained();
            $table->string('title', 200);
            $table->text('description');
            $table->json('learning_objectives')->nullable();
            $table->text('heritage_link')->nullable();
            $table->json('deliverables');
            $table->json('resources')->nullable();
            $table->date('starts_on');
            $table->date('due_on');
            $table->decimal('max_mark', 6, 2);
            $table->foreignId('rubric_id')->constrained('project_rubrics');
            $table->unsignedBigInteger('brief_document_id')->nullable();
            $table->string('status', 20);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'subject_id', 'grade_level_id', 'instrument_id'], 'project_briefs_unique');
            $table->index(['school_id', 'academic_year_id', 'status'], 'project_briefs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_briefs');
    }
};
